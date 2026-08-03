"""
Stage: Extracting Lyrics.

Tries LRCLIB -> Musixmatch -> Genius for the lyrics text first (fastest,
most accurate when a match exists). Falls back to transcribing the isolated
vocals track with WhisperX when nothing is found. Either way, this stage
only produces *text* (plus rough segment timing when WhisperX did the
transcribing) — word-level timestamps are the "Synchronizing Lyrics" stage's
job, since none of the text providers give word timing.
"""

from __future__ import annotations

import json
from pathlib import Path
from typing import Any

from services import lyrics_providers
from services.status_writer import StatusWriter
from services.whisperx_engine import select_device, transcribe


def run(config: dict[str, Any], status: StatusWriter) -> dict[str, Any]:
    job_dir = Path(config["job_dir"])
    metadata = status.snapshot().get("metadata") or {}
    youtube_title = metadata.get("title") or "Unknown"
    channel = metadata.get("channel") or ""

    artist, title = lyrics_providers.guess_artist_title(youtube_title, channel)
    status.log(f"Looking up lyrics for artist=\"{artist}\" title=\"{title}\"")

    text, source = lyrics_providers.get_lyrics_text(
        artist,
        title,
        metadata.get("duration_sec"),
        config.get("musixmatch_api_key", ""),
        config.get("genius_access_token", ""),
    )

    # "auto" (or unset) leaves WhisperX's own language auto-detection in
    # charge; anything else pins it — worth doing for short/noisy clips
    # where auto-detect is unreliable (this is exactly what surfaced a real
    # Tamil song getting auto-detected as English).
    configured_language = config.get("transcription_language") or "auto"
    forced_language = None if configured_language == "auto" else configured_language

    result: dict[str, Any]

    if text:
        status.log(f"Found lyrics via {source}")
        # Provider text carries no language tag of its own — use the pinned
        # language if the admin set one, otherwise identify it from the
        # text's own script (reliable, unlike audio-based detection: a
        # Malayalam lyrics match must actually contain Malayalam
        # characters). Getting this right matters downstream — the
        # "Synchronizing Lyrics" stage picks its forced-alignment model
        # by this language code, and a wrong one there means loading an
        # alignment model for a language the text isn't even written in.
        detected_language = forced_language or lyrics_providers.detect_text_language(text)
        status.log(f"Lyrics text language: {detected_language}")
        result = {"text": text, "source": source, "segments": None, "language": detected_language}
    else:
        status.log("No lyrics match found in any provider — transcribing vocals with WhisperX")

        vocals_path = job_dir / "vocals.wav"
        whisperx_model = config.get("whisperx_model") or "medium"

        transcription = transcribe(str(vocals_path), whisperx_model, device=select_device(), language=forced_language)
        segments = transcription.get("segments", [])
        text = " ".join(segment.get("text", "").strip() for segment in segments).strip()
        language = transcription.get("language", "en")

        status.log(f"Transcribed {len(segments)} segment(s), language={language}")

        result = {"text": text, "source": "whisperx", "segments": segments, "language": language}

    (job_dir / "lyrics_raw.json").write_text(json.dumps(result, indent=2), encoding="utf-8")

    return result
