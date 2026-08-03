"""
Final video assembly: selected AI background (Ken Burns pan/zoom) +
word-highlighted karaoke subtitles (burned in via libass) + instrumental (or
full-mix, for "keep vocals" lyric-video mode) audio, encoded as H.264/AAC
1920x1080 MP4.
"""

from __future__ import annotations

import json
from pathlib import Path
from typing import Any

from services.ass_builder import write_ass_file
from services.ffmpeg_utils import FfmpegError, probe_duration_seconds, run_ffmpeg
from services.status_writer import StatusWriter

RESOLUTION = (1920, 1080)
FPS = 30


class RenderError(Exception):
    pass


def _escape_for_filter(path: str) -> str:
    """ffmpeg filter-graph option values treat ':' and '\\' specially, so a
    Windows path like "C:\\foo\\bar.ass" needs escaping before it can be
    passed inside a `subtitles=...` filter argument."""

    posix = path.replace("\\", "/")
    escaped = posix.replace(":", r"\:")

    return escaped


def run(config: dict[str, Any], status: StatusWriter) -> dict[str, Any]:
    job_dir = Path(config["job_dir"])

    selection_path = job_dir / "selection.json"
    if not selection_path.exists():
        raise RenderError("No background was selected — cannot render.")

    selection = json.loads(selection_path.read_text(encoding="utf-8"))
    background_path = job_dir / selection["image_path"]

    if not background_path.exists():
        raise RenderError(f"Selected background image not found: {background_path}")

    words_path = job_dir / "lyrics_words.json"
    words = json.loads(words_path.read_text(encoding="utf-8")) if words_path.exists() else []

    keep_vocals = bool(config.get("keep_vocals"))
    audio_path = job_dir / ("audio.wav" if keep_vocals else "instrumental.wav")

    if not audio_path.exists():
        # Fall back gracefully so a job that skipped separation (e.g. an
        # instrumental-only source) can still render.
        audio_path = job_dir / "audio.wav"

    if not audio_path.exists():
        raise RenderError(f"No audio track found to render (expected {audio_path}).")

    status.log(f"Using {'full mix (keep vocals)' if keep_vocals else 'instrumental'} audio track")

    duration = probe_duration_seconds(str(audio_path), config)

    if duration <= 0:
        raise RenderError("Could not determine audio duration for rendering.")

    ass_path = job_dir / "karaoke.ass"
    write_ass_file(words, str(ass_path), RESOLUTION)
    status.log(f"Generated subtitle file with {len(words)} word(s)")

    output_path = job_dir / "karaoke.mp4"
    width, height = RESOLUTION
    total_frames = max(1, int(duration * FPS))

    # Upscale generously before zoompan so the slow pan/zoom stays sharp
    # instead of magnifying compression artifacts from the source image.
    zoompan = (
        f"scale=3840:2160:force_original_aspect_ratio=increase,"
        f"crop=3840:2160,"
        f"zoompan=z='min(zoom+0.0006,1.25)':d={total_frames}:"
        f"x='iw/2-(iw/zoom/2)':y='ih/2-(ih/zoom/2)':s={width}x{height}:fps={FPS}"
    )

    subtitles_arg = f"subtitles='{_escape_for_filter(str(ass_path))}'"

    filter_complex = f"[0:v]{zoompan},format=yuv420p[bg];[bg]{subtitles_arg}[v]"

    args = [
        "-loop", "1",
        "-i", str(background_path),
        "-i", str(audio_path),
        "-filter_complex", filter_complex,
        "-map", "[v]",
        "-map", "1:a",
        "-t", f"{duration:.3f}",
        "-r", str(FPS),
        "-c:v", "libx264",
        "-preset", "medium",
        "-crf", "20",
        "-pix_fmt", "yuv420p",
        "-c:a", "aac",
        "-b:a", "192k",
        "-shortest",
        str(output_path),
    ]

    status.log("Rendering final video with ffmpeg (Ken Burns + karaoke subtitles)…")

    try:
        run_ffmpeg(args, config, status, log_label="video render")
    except FfmpegError as exc:
        raise RenderError(str(exc)) from exc

    if not output_path.exists():
        raise RenderError("ffmpeg reported success but no output file was found.")

    file_size = output_path.stat().st_size
    status.log(f"Render complete: {output_path.name} ({file_size / 1_048_576:.1f} MB)")

    return {
        "video_path": str(output_path),
        "duration_sec": round(duration, 2),
        "resolution": f"{width}x{height}",
        "file_size_bytes": file_size,
    }
