"""
Stage: Extracting Lyrics.

Uses the lyrics the user pasted or uploaded when there are any. Otherwise
tries LRCLIB -> Musixmatch -> Genius for the lyrics text (fastest, most
accurate when a match exists), and falls back to transcribing the isolated
vocals track with WhisperX when nothing is found. Either way, this stage only
produces *text* (plus line timing when an .lrc file or WhisperX gives it) —
word-level timestamps are the "Synchronizing Lyrics" stage's job.
"""

from __future__ import annotations

import json
from pathlib import Path
from typing import Any

from services import lyrics_providers
from services.status_writer import StatusWriter
from services.whisperx_engine import select_device, transcribe

# Languages whose lyrics are often written in English letters ("Poove
# sempoove..."). Their alignment models only know their own script.
_INDIAN_LANGUAGES = ("ta", "ml", "hi")


def _transcribe_vocals(
    config: dict[str, Any], status: StatusWriter, job_dir: Path, language: str | None
) -> dict[str, Any]:
    vocals_path = job_dir / "vocals.wav"
    whisperx_model = config.get("whisperx_model") or "medium"

    transcription = transcribe(str(vocals_path), whisperx_model, device=select_device(), language=language)
    segments = transcription.get("segments", [])
    text = " ".join(segment.get("text", "").strip() for segment in segments).strip()
    detected_language = transcription.get("language", "en")

    status.log(f"Transcribed {len(segments)} segment(s), language={detected_language}")

    return {"text": text, "source": "whisperx", "segments": segments, "language": detected_language}


def _pasted_lyrics(config: dict[str, Any], duration_sec: int | None) -> tuple[str | None, list[dict[str, Any]] | None]:
    raw = str(config.get("custom_lyrics") or "")
    text = lyrics_providers.strip_lrc_timestamps(raw)

    if not text:
        return None, None

    return text, lyrics_providers.parse_lrc_segments(raw, duration_sec)


def run(config: dict[str, Any], status: StatusWriter) -> dict[str, Any]:
    job_dir = Path(config["job_dir"])
    metadata = status.snapshot().get("metadata") or {}
    duration_sec = metadata.get("duration_sec")

    text, segments = _pasted_lyrics(config, duration_sec)

    if text:
        source = "user"
        status.log(f"Using the lyrics provided with the job ({len(text.splitlines())} line(s))")
    else:
        if config.get("custom_lyrics"):
            status.log("The lyrics provided with the job had no lyric lines — looking them up instead")

        youtube_title = metadata.get("title") or "Unknown"
        artist, title = lyrics_providers.guess_artist_title(youtube_title, metadata.get("channel") or "")
        status.log(f"Looking up lyrics for artist=\"{artist}\" title=\"{title}\"")

        text, source, segments = lyrics_providers.get_lyrics_text(
            artist,
            title,
            duration_sec,
            config.get("musixmatch_api_key", ""),
            config.get("genius_access_token", ""),
            status.log,
        )

        if text:
            status.log(f"Found lyrics via {source}")

    # "auto" (or unset) leaves WhisperX's own language auto-detection in
    # charge; anything else pins it — worth doing for short/noisy clips
    # where auto-detect is unreliable (this is exactly what surfaced a real
    # Tamil song getting auto-detected as English).
    configured_language = config.get("transcription_language") or "auto"
    forced_language = None if configured_language == "auto" else configured_language

    if text:
        # Text carries no language tag of its own — use the pinned language
        # if the user set one, otherwise identify it from the text's own
        # script (reliable, unlike audio-based detection: Malayalam lyrics
        # must actually contain Malayalam characters). The "Synchronizing
        # Lyrics" stage picks its forced-alignment model by this code.
        text_language = lyrics_providers.detect_text_language(text)
        language = forced_language or text_language

        # Lyrics in English letters are kept as written, and timed with the
        # English model, which reads those letters.
        if language in _INDIAN_LANGUAGES and text_language == "en":
            status.log(f"Lyrics for a '{language}' song are in English letters — keeping them and aligning with the English model")
            language = "en"

        status.log(f"Lyrics text language: {language}")

        if segments:
            status.log(f"Using the line timing from the {'uploaded .lrc file' if source == 'user' else source} for {len(segments)} line(s)")

        result = {"text": text, "source": source, "segments": segments, "language": language}
    else:
        status.log("No lyrics match found in any provider — transcribing vocals with WhisperX")
        result = _transcribe_vocals(config, status, job_dir, forced_language)

    (job_dir / "lyrics_raw.json").write_text(json.dumps(result, indent=2), encoding="utf-8")

    return result
