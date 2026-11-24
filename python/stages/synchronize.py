"""
Stage: Synchronizing Lyrics.

Turns the lyrics text from the previous stage into word-level timestamps
(`[{"word", "start", "end"}, ...]`) against the isolated vocals track.
Lyrics text (pasted by the user or from a provider) is aligned across the
whole song at once with the MMS aligner, falling back to WhisperX's
per-line alignment if that fails. WhisperX's own transcription already has
phrase timing, so it's aligned with WhisperX as before. The result is
written into status.json's "lyrics" field, which PHP's polling endpoint
persists into the `lyrics` table — this stage never touches MySQL itself.
"""

from __future__ import annotations

import json
from pathlib import Path
from typing import Any

from services.ffmpeg_utils import probe_duration_seconds
from services.mms_aligner import align_lines
from services.status_writer import StatusWriter
from services.whisperx_engine import (
    align_words,
    build_segments_from_text,
    number_lines,
    place_lines_on_singing,
    select_device,
    snap_lines_to_singing,
    spread_bunched_lines,
)


def _align_in_windows(
    config: dict[str, Any],
    status: StatusWriter,
    vocals_path: Path,
    text: str,
    source: str,
    segments: list[dict[str, Any]] | None,
    language: str,
) -> list[dict[str, Any]]:
    """WhisperX alignment, which only searches for each line's words inside
    that line's time window."""

    # Segments that are lyric lines, as opposed to WhisperX's own
    # phrases, which don't follow the lyrics' line breaks.
    has_line_timing = bool(segments) and source != "whisperx"

    if has_line_timing:
        segments, moved = snap_lines_to_singing(str(vocals_path), segments)
        status.log(f"Checked the line timing against the vocals: moved {moved} line(s) with no singing")

    if not segments:
        segments = place_lines_on_singing(str(vocals_path), text)

        if segments:
            has_line_timing = True
            status.log(f"Placed {len(segments)} line(s) across the sung parts of the song")
        else:
            duration = probe_duration_seconds(str(vocals_path), config)
            segments = build_segments_from_text(text, duration)
            status.log(f"No singing detected — spread {len(segments)} line(s) across the whole song")

    status.log("Running WhisperX forced alignment…")
    words, used_forced_alignment = align_words(str(vocals_path), segments, language, device=select_device())

    if used_forced_alignment:
        status.log(f"Aligned {len(words)} word(s)")

        if has_line_timing:
            words, respread = spread_bunched_lines(str(vocals_path), segments, words)
            status.log(f"Respread the words of {respread} bunched line(s) across their sung time")
    else:
        status.log(
            f"No forced-alignment model available for language '{language}' — estimated "
            f"{len(words)} word timestamp(s) by spreading words evenly across each phrase instead "
            "(less precise than real alignment, still usable)"
        )

    if has_line_timing:
        words = number_lines(segments, words)

    return words


def run(config: dict[str, Any], status: StatusWriter) -> dict[str, Any]:
    job_dir = Path(config["job_dir"])
    vocals_path = job_dir / "vocals.wav"

    raw_path = job_dir / "lyrics_raw.json"
    raw = json.loads(raw_path.read_text(encoding="utf-8"))

    text = raw.get("text", "")
    source = raw.get("source", "unknown")
    segments = raw.get("segments")
    language = raw.get("language") or "en"
    words: list[dict[str, Any]] = []

    if not text.strip():
        status.log("No lyrics text available — skipping word-level sync")
    elif source == "whisperx":
        words = _align_in_windows(config, status, vocals_path, text, source, segments, language)
    else:
        lines = [line.strip() for line in text.splitlines() if line.strip()]
        status.log(f"Aligning {len(lines)} lyric line(s) across the whole song with the MMS aligner…")

        try:
            words = align_lines(str(vocals_path), lines, language, device=select_device())
            status.log(f"Aligned {len(words)} word(s) to the vocals")
        except Exception as exc:  # noqa: BLE001 - any failure here has a working fallback
            status.log(f"Whole-song alignment failed ({exc}) — aligning line by line instead")
            words = _align_in_windows(config, status, vocals_path, text, source, segments, language)

    (job_dir / "lyrics_words.json").write_text(json.dumps(words, indent=2), encoding="utf-8")

    status.update(lyrics={"source": source, "raw_text": text, "words": words})

    return {"words": words, "source": source}
