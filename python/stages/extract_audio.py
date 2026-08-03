"""
Stage: Extracting Audio.

Normalizes whatever container yt-dlp downloaded (webm/m4a/opus) into a
standard 44.1kHz stereo WAV — the format Demucs and WhisperX both expect.
"""

from __future__ import annotations

from pathlib import Path
from typing import Any

from services.ffmpeg_utils import run_ffmpeg
from services.status_writer import StatusWriter


def run(config: dict[str, Any], status: StatusWriter) -> dict[str, Any]:
    job_dir = Path(config["job_dir"])

    candidates = sorted(p for p in job_dir.glob("source_audio.*") if p.suffix != ".wav")

    if not candidates:
        # Already a WAV (or download stage produced one directly).
        candidates = sorted(job_dir.glob("source_audio.*"))

    if not candidates:
        raise FileNotFoundError("No downloaded audio file found to extract from.")

    source_path = candidates[0]
    output_path = job_dir / "audio.wav"

    run_ffmpeg(
        ["-i", str(source_path), "-ac", "2", "-ar", "44100", "-vn", str(output_path)],
        config,
        status,
        log_label="audio extraction",
    )

    status.log(f"Extracted normalized audio to {output_path.name}")

    return {"audio_path": str(output_path)}
