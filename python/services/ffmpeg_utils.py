"""Shared helper for invoking ffmpeg/ffprobe as subprocesses with logging."""

from __future__ import annotations

import json
import subprocess
from pathlib import Path
from typing import Any

from services.status_writer import StatusWriter


class FfmpegError(Exception):
    pass


def run_ffmpeg(args: list[str], config: dict[str, Any], status: StatusWriter, log_label: str = "ffmpeg") -> None:
    """Run ffmpeg with the given args (excluding the binary itself), raising
    FfmpegError with the tail of stderr on non-zero exit."""

    ffmpeg_path = config.get("ffmpeg_path") or "ffmpeg"
    command = [ffmpeg_path, "-y", "-hide_banner", "-loglevel", "error", *args]

    status.log(f"Running {log_label}…")

    result = subprocess.run(
        command,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        text=True,
    )

    if result.returncode != 0:
        tail = "\n".join(result.stderr.strip().splitlines()[-15:])
        raise FfmpegError(f"{log_label} failed (exit {result.returncode}): {tail}")


def probe_duration_seconds(path: str, config: dict[str, Any]) -> float:
    ffprobe_path = config.get("ffprobe_path") or "ffprobe"
    command = [
        ffprobe_path,
        "-v", "error",
        "-show_entries", "format=duration",
        "-of", "json",
        path,
    ]

    result = subprocess.run(command, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)

    if result.returncode != 0:
        raise FfmpegError(f"ffprobe failed: {result.stderr.strip()}")

    data = json.loads(result.stdout or "{}")

    return float(data.get("format", {}).get("duration", 0.0))
