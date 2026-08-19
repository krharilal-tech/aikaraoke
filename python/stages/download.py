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


def _write_cookie_file(job_dir: Path, cookies_text: str) -> str | None:
    """YouTube occasionally bot-blocks datacenter IPs (RunPod's included) with
    "Sign in to confirm you're not a bot" — passing a real logged-in
    session's cookies (Settings -> YouTube Cookies, Netscape cookies.txt
    format) satisfies that check same as a real browser would. Written
    fresh per job rather than referencing a shared path since job_dir is
    an ephemeral per-job tempdir on RunPod, not a fixed location."""

    if not cookies_text.strip():
        return None

    cookie_path = job_dir / "cookies.txt"
    cookie_path.write_text(cookies_text, encoding="utf-8")

    return str(cookie_path)


def run(config: dict[str, Any], status: StatusWriter) -> dict[str, Any]:
    url = config["youtube_url"]
    job_dir = Path(config["job_dir"])
    max_duration = int(config.get("max_video_length_seconds", 600))
    cookie_file = _write_cookie_file(job_dir, str(config.get("youtube_cookies", "")))

    status.log(f"Fetching metadata for {url}")

    probe_opts: dict[str, Any] = {"quiet": True, "no_warnings": True, "noplaylist": True}

    if cookie_file:
        probe_opts["cookiefile"] = cookie_file

    with yt_dlp.YoutubeDL(probe_opts) as probe:
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

    ydl_opts: dict[str, Any] = {
        "format": "bestaudio/best",
        "outtmpl": output_template,
        "noplaylist": True,
        "quiet": True,
        "no_warnings": True,
    }

    if cookie_file:
        ydl_opts["cookiefile"] = cookie_file

    status.log("Downloading best-quality audio…")

    try:
        with yt_dlp.YoutubeDL(ydl_opts) as ydl:
            ydl.download([url])
    except yt_dlp.utils.DownloadError as exc:
        # YouTube's default "web" player client is the one most often
        # 403'd on cloud/datacenter IPs (RunPod's included) at the actual
        # media-CDN step — metadata (the probe above) still comes back
        # fine since that's a lighter-weight, less-gated request. The
        # "android" client gets its stream URLs from a different endpoint
        # that isn't gated the same way, so it's worth a second attempt
        # before giving up, without requiring cookies at all.
        status.log(f"Download failed with default client ({exc}) — retrying via Android client…")

        android_opts = {**ydl_opts, "extractor_args": {"youtube": {"player_client": ["android"]}}}

        try:
            with yt_dlp.YoutubeDL(android_opts) as ydl:
                ydl.download([url])
        except yt_dlp.utils.DownloadError as retry_exc:
            raise DownloadError(f"Download failed: {retry_exc}") from retry_exc

    candidates = sorted(job_dir.glob("source_audio.*"))

    if not candidates:
        raise DownloadError("Download finished but no audio file was found on disk.")

    audio_path = candidates[0]
    status.log(f"Audio saved to {audio_path.name}")

    return {"audio_path": str(audio_path), **metadata}
