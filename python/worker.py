#!/usr/bin/env python3
"""
AI Karaoke Maker — Python worker dispatcher.

Invoked by PHP (App\\Services\\JobService::spawnWorker) as a detached
subprocess: `python worker.py --job-id <id> --root <project root>`.

Responsibilities: run the processing pipeline for one job, stage by stage,
writing progress to storage/jobs/<id>/status.json as it goes. This process
never touches MySQL or .env directly — App\\Services\\JobService::create()
already snapshotted everything this job needs into config.json, and PHP's
polling endpoint is the only thing that reads status.json back into the
database.
"""

from __future__ import annotations

import argparse
import json
import os
import sys
import time
import traceback
from pathlib import Path
from typing import Callable

sys.path.insert(0, str(Path(__file__).resolve().parent))

from services.status_writer import StatusWriter  # noqa: E402
from stages import download as stage_download  # noqa: E402
from stages import extract_audio as stage_extract_audio  # noqa: E402
from stages import extract_lyrics as stage_extract_lyrics  # noqa: E402
from stages import generate_images as stage_generate_images  # noqa: E402
from stages import render_video as stage_render_video  # noqa: E402
from stages import separate_vocals as stage_separate_vocals  # noqa: E402
from stages import synchronize as stage_synchronize  # noqa: E402

# (state, label, progress % at stage start, progress % at stage end)
#
# There used to be a "waiting_for_user" stage here between generating_images
# and rendering_video, pausing the job until a human picked one of the 3
# background candidates in the browser. It's gone — generate_images.py now
# auto-selects the first candidate itself and writes selection.json
# immediately, so rendering starts right away with no pause.
PIPELINE: list[tuple[str, str, int, int]] = [
    ("downloading", "Downloading YouTube Video", 2, 15),
    ("extracting_audio", "Extracting Audio", 15, 25),
    ("separating_vocals", "Removing Vocals", 25, 45),
    ("extracting_lyrics", "Extracting Lyrics", 45, 60),
    ("synchronizing", "Synchronizing Lyrics", 60, 68),
    ("generating_images", "Generating AI Backgrounds", 68, 85),
    ("rendering_video", "Rendering Karaoke Video", 85, 99),
]

STAGE_HANDLERS = {
    "downloading": stage_download.run,
    "extracting_audio": stage_extract_audio.run,
    "separating_vocals": stage_separate_vocals.run,
    "extracting_lyrics": stage_extract_lyrics.run,
    "synchronizing": stage_synchronize.run,
    "generating_images": stage_generate_images.run,
    "rendering_video": stage_render_video.run,
}


def load_config(job_dir: Path) -> dict:
    config_path = job_dir / "config.json"

    if not config_path.exists():
        raise FileNotFoundError(f"config.json not found in {job_dir}")

    return json.loads(config_path.read_text(encoding="utf-8"))


def put_ffmpeg_on_path(config: dict) -> None:
    """Some libraries (WhisperX's internal audio loader, among others) shell
    out to a bare "ffmpeg"/"ffprobe" rather than accepting a configurable
    path, so our Settings-configured ffmpeg_path only helps our own
    subprocess calls unless its directory is also on PATH for this process
    (and everything it spawns)."""

    ffmpeg_path = config.get("ffmpeg_path")

    if not ffmpeg_path:
        return

    ffmpeg_dir = str(Path(ffmpeg_path).resolve().parent)

    if ffmpeg_dir not in os.environ.get("PATH", ""):
        os.environ["PATH"] = ffmpeg_dir + os.pathsep + os.environ.get("PATH", "")


def run_pipeline(
    stages: list[tuple[str, str, int, int]],
    config: dict,
    status: StatusWriter,
    before_completion: Callable[[], None] | None = None,
) -> int:
    """Runs a sequence of (state, label, start_pct, end_pct) pipeline stages
    in order against an already-loaded config/status pair, then marks the
    job completed. Used both for a fresh job (main(), given the full
    PIPELINE) and for resuming a job partway through (resume_job.py, given
    a slice of PIPELINE) — both need the exact same crash-hardened failure
    handling below, so it lives in one place rather than two.

    before_completion runs after every stage finishes but before the
    "completed" status is sent — handler.py uses this to upload the
    finished video to PHP first, so the app never reports a job as
    complete before the video file actually exists on Hostinger. If it
    raises, the exception falls through to the same failure handling as
    a stage raising (the job is marked failed, not falsely completed)."""

    try:
        for state, label, start_pct, end_pct in stages:
            status.update(state=state, stage_label=label, progress_percent=start_pct, message=f"{label}…")
            status.log(f"Starting stage: {label}")

            STAGE_HANDLERS[state](config, status)

            status.update(progress_percent=end_pct)
            status.log(f"Finished stage: {label}")

        if before_completion is not None:
            before_completion()

        status.update(
            state="completed",
            stage_label="Completed",
            progress_percent=100,
            message="Your karaoke video is ready.",
            eta_seconds=0,
            error=None,  # clear any stale error from an earlier failed attempt on this same job (e.g. resume_job.py)
        )
        status.log("Job completed successfully")

        return 0
    except Exception as exc:
        tb = traceback.format_exc()

        try:
            status.update(state="failed", error=str(exc), message="Job failed.")
            status.log(f"ERROR: {exc}")
            status.log(tb)
        except Exception as status_exc:
            # If even *reporting* the failure fails (this has happened for
            # real: the same transient Windows file-lock that caused the
            # original error also hit the failure-reporting write before
            # StatusWriter had its current, much larger retry budget +
            # non-atomic fallback), don't let the job vanish into a
            # "stuck" state with zero record of what went wrong. Skip
            # StatusWriter entirely and force the bytes onto disk directly.
            print(f"ERROR: {exc}\n{tb}", file=sys.stderr)
            print(f"Also failed to write status.json: {status_exc}", file=sys.stderr)

            try:
                fallback = {
                    "state": "failed",
                    "progress_percent": 0,
                    "error": str(exc),
                    "message": "Job failed (status file was also unwritable — see worker.log).",
                    "updated_at": time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime()),
                }
                status.status_path.write_text(json.dumps(fallback, indent=2), encoding="utf-8")
            except Exception as fallback_exc:
                print(f"Also failed to write the fallback status file: {fallback_exc}", file=sys.stderr)

        return 1


def main() -> int:
    parser = argparse.ArgumentParser(description="AI Karaoke Maker job worker")
    parser.add_argument("--job-id", type=int, required=True)
    parser.add_argument("--root", type=str, required=True)
    args = parser.parse_args()

    job_dir = Path(args.root) / "storage" / "jobs" / str(args.job_id)
    status = StatusWriter(job_dir)

    try:
        config = load_config(job_dir)
    except Exception as exc:  # config.json missing/corrupt — nothing else to do
        status.update(state="failed", error=str(exc), message="Failed to start job.")
        status.log(f"ERROR: {exc}")
        return 1

    put_ffmpeg_on_path(config)
    status.log(f"Worker started for job #{args.job_id}")

    return run_pipeline(PIPELINE, config, status)


if __name__ == "__main__":
    sys.exit(main())
