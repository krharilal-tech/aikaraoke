"""
Stage: Downloading YouTube Video.

Fetches metadata first (to validate duration before spending time on a
download), then pulls the best available audio-only stream. Audio is saved
in whatever container yt-dlp gives us (webm/m4a/opus) — the "Extracting
Audio" stage normalizes it to WAV via ffmpeg, so this stage has no ffmpeg
dependency of its own.
"""

from __future__ import annotations

import shutil
import subprocess
from pathlib import Path
from typing import Any

import yt_dlp

from services.status_writer import StatusWriter


class DownloadError(Exception):
    pass


class _YdlLogger:
    """Routes yt-dlp's own diagnostics into status.log instead of letting
    "quiet"/"no_warnings" swallow them. This matters specifically for the
    PO token provider (Dockerfile/docker-entrypoint.sh): when it isn't
    reachable or isn't producing a valid token, yt-dlp doesn't say so
    directly — it silently drops the formats that needed one and then
    fails with the generic "Requested format is not available", which by
    itself gives no way to tell "PO token problem" apart from any other
    cause. The warning that actually explains *why* formats got dropped
    only surfaces if something is listening for it. Debug lines are
    filtered to plugin/PO-token-related ones — full debug output is
    mostly per-request noise that isn't worth mirroring into the DB via
    StatusWriter.log()."""

    def __init__(self, status: StatusWriter, label: str) -> None:
        self._status = status
        self._label = label

    _DEBUG_KEYWORDS = ("pot", "po token", "plugin", "deno", "node", "runtime", "ejs", "challenge", "js-runtime")

    def debug(self, msg: str) -> None:
        lowered = msg.lower()
        if any(keyword in lowered for keyword in self._DEBUG_KEYWORDS):
            self._status.log(f"[yt-dlp {self._label} debug] {msg}")

    def warning(self, msg: str) -> None:
        self._status.log(f"[yt-dlp {self._label} warning] {msg}")

    def error(self, msg: str) -> None:
        self._status.log(f"[yt-dlp {self._label} error] {msg}")


def _write_cookie_file(job_dir: Path, cookies_text: str) -> str | None:
    """Optional extra layer on top of the PO token provider (Dockerfile +
    docker-entrypoint.sh) that now handles YouTube's "Sign in to confirm
    you're not a bot" check on its own, cookie-free. Left in as a fallback
    for Settings -> YouTube Cookies (Netscape cookies.txt format) since a
    real logged-in session still works too — just don't rely on it as the
    primary fix: Google rotates session tokens independently of their
    listed expiry, so a static export goes stale within days regardless of
    how carefully it was exported. Written fresh per job rather than
    referencing a shared path since job_dir is an ephemeral per-job
    tempdir on RunPod, not a fixed location."""

    if not cookies_text.strip():
        return None

    cookie_path = job_dir / "cookies.txt"
    cookie_path.write_text(cookies_text, encoding="utf-8")

    return str(cookie_path)


def _log_deno_availability(status: StatusWriter) -> None:
    """Direct, unambiguous check for the JS runtime yt-dlp's signature/n-
    parameter solver ("EJS") needs — not dependent on catching or
    correctly guessing the wording of yt-dlp's own internal debug output,
    which is what actually hid the real problem the first time around
    (see the Dockerfile's Deno install for context)."""

    deno_path = shutil.which("deno")
    status.log(f"[env check] deno on PATH: {deno_path or 'NOT FOUND'}")

    if not deno_path:
        return

    try:
        result = subprocess.run(
            [deno_path, "--version"], capture_output=True, text=True, timeout=10
        )
        status.log(
            f"[env check] deno --version (exit {result.returncode}): "
            f"{result.stdout.strip() or result.stderr.strip()}"
        )
    except Exception as exc:  # noqa: BLE001 - diagnostic only, any failure is worth surfacing
        status.log(f"[env check] deno --version failed to run: {exc}")


def run(config: dict[str, Any], status: StatusWriter) -> dict[str, Any]:
    url = config["youtube_url"]
    job_dir = Path(config["job_dir"])
    max_duration = int(config.get("max_video_length_seconds", 600))
    cookie_file = _write_cookie_file(job_dir, str(config.get("youtube_cookies", "")))

    _log_deno_availability(status)

    status.log(f"Fetching metadata for {url}")

    probe_opts: dict[str, Any] = {
        "quiet": True,
        "verbose": True,
        "noplaylist": True,
        "logger": _YdlLogger(status, "probe"),
        # Deno alone (Dockerfile) only gets a JS runtime on PATH — yt-dlp
        # still needs explicit opt-in to actually download the challenge
        # solver script it runs *in* that runtime ("EJS"), which is off by
        # default. Without this, every non-image format silently
        # disappears with no clearer error than "Requested format is not
        # available", which is what having both a working PO token *and*
        # a working Deno install still wasn't enough to fix.
        "remote_components": "ejs:github",
    }

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
        "verbose": True,
        "logger": _YdlLogger(status, "download"),
        "remote_components": "ejs:github",
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

        android_opts = {
            **ydl_opts,
            "extractor_args": {"youtube": {"player_client": ["android"]}},
            "logger": _YdlLogger(status, "download-android-retry"),
        }

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
