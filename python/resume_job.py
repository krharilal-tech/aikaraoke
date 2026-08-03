#!/usr/bin/env python3
"""
One-off utility: resume a job from a specific pipeline stage onward,
reusing whatever earlier-stage output already exists on disk instead of
redoing it.

Written for a real incident: a job crashed (a Windows file-locking bug in
status.json writes, since fixed in services/status_writer.py) *after*
Demucs had already finished separating vocals — 20+ minutes of CPU work
already sitting in vocals.wav/instrumental.wav. Re-running the job fresh
via the normal web UI would throw that away and redo it from scratch. This
script instead starts the pipeline directly at whichever stage you name,
skipping every stage before it, and writes to the same status.json/
config.json the normal worker uses — so the job's progress page picks up
right where this leaves off, same as any other running job.

Usage:
    python resume_job.py --job-id 1006 --root C:\\wamp64\\www\\aikaraoke --from-stage extracting_lyrics

This does NOT verify that the earlier stages' output files actually exist
— only run it when you've confirmed that yourself (e.g. vocals.wav and
instrumental.wav are present if resuming from extracting_lyrics onward).
"""

from __future__ import annotations

import argparse
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))

from services.status_writer import StatusWriter  # noqa: E402
from worker import PIPELINE, load_config, put_ffmpeg_on_path, run_pipeline  # noqa: E402


def main() -> int:
    stage_names = [state for state, _, _, _ in PIPELINE]

    parser = argparse.ArgumentParser(description="Resume a job from a specific pipeline stage")
    parser.add_argument("--job-id", type=int, required=True)
    parser.add_argument("--root", type=str, required=True)
    parser.add_argument("--from-stage", required=True, choices=stage_names)
    args = parser.parse_args()

    job_dir = Path(args.root) / "storage" / "jobs" / str(args.job_id)

    if not job_dir.is_dir():
        print(f"Job directory not found: {job_dir}", file=sys.stderr)
        return 1

    status = StatusWriter(job_dir)
    config = load_config(job_dir)
    put_ffmpeg_on_path(config)

    start_index = stage_names.index(args.from_stage)
    remaining = PIPELINE[start_index:]

    status.log(
        f"Resuming job #{args.job_id} from stage '{args.from_stage}' "
        f"(skipping {start_index} already-completed stage(s))"
    )

    return run_pipeline(remaining, config, status)


if __name__ == "__main__":
    sys.exit(main())
