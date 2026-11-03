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
from typing import Any, Callable
from urllib.parse import urlsplit

import yt_dlp

from services.status_writer import StatusWriter


# Tried in order when the default client's metadata fetch or download fails,
# always without cookies. A stale cookie export (Settings -> YouTube Cookies)
# can get the stream rejected on its own, and neither fallback needs one: mweb
# is what yt-dlp's PO Token Guide recommends pairing with a provider
# (Dockerfile), android_vr doesn't need a PO token at all, and android has
# rescued real jobs here once cookies were out of the way (job 58).
FALLBACK_PLAYER_CLIENTS = ("mweb", "android_vr", "android")

# What YouTube refusing the worker looks like, as opposed to a problem with
# the video itself. Customers see the failure message, so these get a plain
# explanation instead of yt-dlp's advice about passing cookies.
_YOUTUBE_BLOCK_MARKERS = ("not a bot", "http error 403")
_YOUTUBE_BLOCK_MESSAGE = (
    "YouTube blocked this download from our servers. Any credit used has been "
    "refunded — please try again in a little while."
)


class DownloadError(Exception):
    pass


class _RedactedStatus:
    """Keeps the proxy's username and password out of the job log. yt-dlp's
    debug output prints its full options, including the proxy URL."""

    def __init__(self, status: StatusWriter, proxy: str) -> None:
        self._status = status
        credentials = urlsplit(proxy).netloc.rpartition("@")[0]
        self._secrets = [secret for secret in (credentials, credentials.partition(":")[2]) if secret]

    def __getattr__(self, name: str) -> Any:
        return getattr(self._status, name)

    def redact(self, text: str) -> str:
        for secret in self._secrets:
            text = text.replace(secret, "***")

        return text

    def log(self, message: str) -> None:
        self._status.log(self.redact(message))


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


def _run_with_fallback_clients(
    opts: dict[str, Any],
    status: _RedactedStatus,
    label: str,
    failure: str,
    action: Callable[[yt_dlp.YoutubeDL], Any],
) -> Any:
    """Runs `action` with the default client, then with each fallback client.
    YouTube's "Sign in to confirm you're not a bot" (job 60) and 403s from the
    media CDN both depend on the client as well as the worker's IP, so one
    failure isn't final."""

    try:
        with yt_dlp.YoutubeDL(opts) as ydl:
            return action(ydl)
    except yt_dlp.utils.DownloadError as exc:
        last_exc = exc

    for client in FALLBACK_PLAYER_CLIENTS:
        status.log(f"{failure} ({last_exc}) — retrying via {client} client without cookies…")

        fallback_opts = {
            **opts,
            "extractor_args": {"youtube": {"player_client": [client]}},
            "logger": _YdlLogger(status, f"{label}-{client}-retry"),
        }
        fallback_opts.pop("cookiefile", None)

        try:
            with yt_dlp.YoutubeDL(fallback_opts) as ydl:
                return action(ydl)
        except yt_dlp.utils.DownloadError as exc:
            last_exc = exc

    details = status.redact(f"{failure}: {last_exc}")
    status.log(details)

    # "from None": the worker logs this traceback, and the original error
    # could carry the proxy's credentials.
    if any(marker in details.lower() for marker in _YOUTUBE_BLOCK_MARKERS):
        raise DownloadError(_YOUTUBE_BLOCK_MESSAGE) from None

    raise DownloadError(details) from None


def run(config: dict[str, Any], status: StatusWriter) -> dict[str, Any]:
    url = config["youtube_url"]
    job_dir = Path(config["job_dir"])
    max_duration = int(config.get("max_video_length_seconds", 600))
    cookie_file = _write_cookie_file(job_dir, str(config.get("youtube_cookies", "")))
    # A stream link only works from the IP that requested it, so each job
    # needs one IP throughout, and its own session so a flagged IP doesn't
    # follow every later job.
    proxy = str(config.get("youtube_proxy") or "").strip().replace("{session}", f"job{config.get('job_id')}")
    status = _RedactedStatus(status, proxy)

    status.log(f"[env check] yt-dlp version: {yt_dlp.version.__version__}")
    _log_deno_availability(status)

    if proxy:
        status.log(f"Using YouTube proxy: {urlsplit(proxy).hostname}")

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
        # Must be a list, not a bare string — yt-dlp does set(value)
        # internally, and set() on a plain string iterates its individual
        # characters rather than treating it as one item. Confirmed by the
        # exact failure this produced: "Ignoring unsupported remote
        # component(s): s, :, j, g, u, b, e, t, h, i" — literally every
        # character of "ejs:github".
        "remote_components": ["ejs:github"],
    }

    if cookie_file:
        probe_opts["cookiefile"] = cookie_file

    if proxy:
        probe_opts["proxy"] = proxy

    info = _run_with_fallback_clients(
        probe_opts,
        status,
        "probe",
        "Could not fetch video",
        lambda ydl: ydl.extract_info(url, download=False),
    )

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
        # Must be a list, not a bare string — yt-dlp does set(value)
        # internally, and set() on a plain string iterates its individual
        # characters rather than treating it as one item. Confirmed by the
        # exact failure this produced: "Ignoring unsupported remote
        # component(s): s, :, j, g, u, b, e, t, h, i" — literally every
        # character of "ejs:github".
        "remote_components": ["ejs:github"],
    }

    if cookie_file:
        ydl_opts["cookiefile"] = cookie_file

    if proxy:
        ydl_opts["proxy"] = proxy

    status.log("Downloading best-quality audio…")

    # The media-CDN step can 403 even though metadata (the probe above) came
    # back fine, since that's a lighter-weight, less-gated request.
    _run_with_fallback_clients(ydl_opts, status, "download", "Download failed", lambda ydl: ydl.download([url]))

    candidates = sorted(job_dir.glob("source_audio.*"))

    if not candidates:
        raise DownloadError("Download finished but no audio file was found on disk.")

    audio_path = candidates[0]
    status.log(f"Audio saved to {audio_path.name}")

    return {"audio_path": str(audio_path), **metadata}
