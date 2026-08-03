"""
Stage: Downloading YouTube Video.

Fetches metadata first (to validate duration before spending time on a
download), then pulls the best available audio-only stream. Audio is saved
in whatever container yt-dlp gives us (webm/m4a/opus) — the "Extracting
Audio" stage normalizes it to WAV via ffmpeg, so this stage has no ffmpeg
dependency of its own.
"""

from __future__ import annotations

from pathlib import Path
from typing import Any

import yt_dlp

from services.status_writer import StatusWriter


class DownloadError(Exception):
    pass


def run(config: dict[str, Any], status: StatusWriter) -> dict[str, Any]:
    url = config["youtube_url"]
    job_dir = Path(config["job_dir"])
    max_duration = int(config.get("max_video_length_seconds", 600))

    status.log(f"Fetching metadata for {url}")

    with yt_dlp.YoutubeDL({"quiet": True, "no_warnings": True, "noplaylist": True}) as probe:
        try:
            info = probe.extract_info(url, download=False)
        except yt_dlp.utils.DownloadError as exc:
            raise DownloadError(f"Could not fetch video: {exc}") from exc

    duration = int(info.get("duration") or 0)

    if duration <= 0:
        raise DownloadError("Could not determine the video's duration.")

    if duration > max_duration:
        raise DownloadError(
            f"Video is {duration // 60}m{duration % 60:02d}s long, which exceeds the "
            f"{max_duration // 60}-minute limit configured in Settings."
        )

    metadata = {
        "title": info.get("title"),
        "channel": info.get("uploader") or info.get("channel"),
        "thumbnail_url": info.get("thumbnail"),
        "duration_sec": duration,
    }
    status.set_metadata(**metadata)
    status.log(f"Found \"{metadata['title']}\" ({duration}s) by {metadata['channel']}")

    output_template = str(job_dir / "source_audio.%(ext)s")

    ydl_opts = {
        "format": "bestaudio/best",
        "outtmpl": output_template,
        "noplaylist": True,
        "quiet": True,
        "no_warnings": True,
    }

    status.log("Downloading best-quality audio…")

    with yt_dlp.YoutubeDL(ydl_opts) as ydl:
        try:
            ydl.download([url])
        except yt_dlp.utils.DownloadError as exc:
            raise DownloadError(f"Download failed: {exc}") from exc

    candidates = sorted(job_dir.glob("source_audio.*"))

    if not candidates:
        raise DownloadError("Download finished but no audio file was found on disk.")

    audio_path = candidates[0]
    status.log(f"Audio saved to {audio_path.name}")

    return {"audio_path": str(audio_path), **metadata}
