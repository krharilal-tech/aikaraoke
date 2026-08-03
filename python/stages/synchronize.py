"""
Stage: Synchronizing Lyrics.

Runs WhisperX forced alignment against the isolated vocals track to turn the
lyrics text from the previous stage into word-level timestamps
(`[{"word", "start", "end"}, ...]`), regardless of whether that text came
from a lyrics provider (no timing at all) or WhisperX's own transcription
(rough segment timing only). The result is written into status.json's
"lyrics" field, which PHP's polling endpoint persists into the `lyrics`
table — this stage never touches MySQL itself.
"""

from __future__ import annotations

import json
from pathlib import Path
from typing import Any

from services.ffmpeg_utils import probe_duration_seconds
from services.status_writer import StatusWriter
from services.whisperx_engine import align_words, build_segments_from_text


def run(config: dict[str, Any], status: StatusWriter) -> dict[str, Any]:
    job_dir = Path(config["job_dir"])
    vocals_path = job_dir / "vocals.wav"

    raw_path = job_dir / "lyrics_raw.json"
    raw = json.loads(raw_path.read_text(encoding="utf-8"))

    text = raw.get("text", "")
    source = raw.get("source", "unknown")
    segments = raw.get("segments")
    language = raw.get("language") or "en"

    if not text.strip():
        status.log("No lyrics text available — skipping word-level sync")
        words: list[dict[str, Any]] = []
    else:
        if not segments:
            duration = probe_duration_seconds(str(vocals_path), config)
            segments = build_segments_from_text(text, duration)
            status.log(f"Built {len(segments)} rough segment(s) from provider text for alignment")

        status.log("Running WhisperX forced alignment…")
        words, used_forced_alignment = align_words(str(vocals_path), segments, language, device="cpu")

        if used_forced_alignment:
            status.log(f"Aligned {len(words)} word(s)")
        else:
            status.log(
                f"No forced-alignment model available for language '{language}' — estimated "
                f"{len(words)} word timestamp(s) by spreading words evenly across each phrase instead "
                "(less precise than real alignment, still usable)"
            )

    (job_dir / "lyrics_words.json").write_text(json.dumps(words, indent=2), encoding="utf-8")

    status.update(lyrics={"source": source, "raw_text": text, "words": words})

    return {"words": words, "source": source}
