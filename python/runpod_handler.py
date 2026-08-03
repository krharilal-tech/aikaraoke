"""
RunPod Serverless entry point — the remote-GPU counterpart to worker.py.

worker.py is invoked locally by PHP as a subprocess and talks back to PHP
through local files (config.json in, status.json out). This does the same
job end to end, just over HTTP instead of a shared filesystem: PHP's
`/run` call to the RunPod endpoint passes everything worker.py would have
read from config.json as the job `input`, and RemoteStatusWriter posts
status updates to a callback URL instead of writing status.json — the
actual pipeline stages (download, separate_vocals, extract_lyrics, ...)
are the exact same code, unmodified, run via the same run_pipeline()
function worker.py and resume_job.py already share.

The one thing worker.py never had to do that this does: fetch the local
background images and upload the finished video back to PHP, since
RunPod's disk is wiped after the job ends and PHP can't reach into it.
"""

from __future__ import annotations

import shutil
import sys
import tempfile
from pathlib import Path
from typing import Any

sys.path.insert(0, str(Path(__file__).resolve().parent))

import requests
import runpod  # noqa: E402

from services.remote_status_writer import RemoteStatusWriter  # noqa: E402
from worker import PIPELINE, put_ffmpeg_on_path, run_pipeline  # noqa: E402

UPLOAD_TIMEOUT_SECONDS = 120
BACKGROUND_FETCH_TIMEOUT_SECONDS = 30


def _download_local_backgrounds(job_dir: Path, callback_base_url: str, worker_secret: str, filenames: list[str]) -> Path:
    """Local-folder background images live on Hostinger, not on this
    container — download them into a real local folder first so
    services/local_backgrounds.py::pick_images() (unmodified) can pick
    from it exactly as it would from storage/backgrounds/ locally."""

    backgrounds_dir = job_dir / "available_backgrounds"
    backgrounds_dir.mkdir(parents=True, exist_ok=True)

    for filename in filenames:
        url = f"{callback_base_url.rstrip('/')}/api/worker/backgrounds/{filename}"
        response = requests.get(
            url,
            headers={"X-Worker-Secret": worker_secret},
            timeout=BACKGROUND_FETCH_TIMEOUT_SECONDS,
        )
        response.raise_for_status()
        (backgrounds_dir / filename).write_bytes(response.content)

    return backgrounds_dir


def _upload_result(job_dir: Path, callback_base_url: str, worker_secret: str, job_id: int) -> None:
    video_path = job_dir / "karaoke.mp4"

    if not video_path.exists():
        raise FileNotFoundError(f"Expected rendered video at {video_path}, but it doesn't exist.")

    url = f"{callback_base_url.rstrip('/')}/api/worker/jobs/{job_id}/upload"

    with open(video_path, "rb") as f:
        response = requests.post(
            url,
            headers={"X-Worker-Secret": worker_secret},
            files={"video": ("karaoke.mp4", f, "video/mp4")},
            timeout=UPLOAD_TIMEOUT_SECONDS,
        )

    response.raise_for_status()


def handler(event: dict[str, Any]) -> dict[str, Any]:
    job_input = event.get("input") or {}

    job_id = int(job_input["job_id"])
    callback_base_url = job_input["callback_base_url"]
    worker_secret = job_input["worker_secret"]

    status = RemoteStatusWriter(job_id, callback_base_url, worker_secret)

    with tempfile.TemporaryDirectory(prefix=f"aikaraoke_job_{job_id}_") as tmp:
        job_dir = Path(tmp)

        config = dict(job_input)
        config["job_dir"] = str(job_dir)
        # These are plain commands on the container's PATH (installed via
        # the Dockerfile), not Windows-style full paths like the WAMP
        # dev config uses.
        config.setdefault("ffmpeg_path", "ffmpeg")
        config.setdefault("ffprobe_path", "ffprobe")

        put_ffmpeg_on_path(config)

        if config.get("background_source") == "local":
            filenames = job_input.get("local_background_filenames") or []
            local_dir = _download_local_backgrounds(job_dir, callback_base_url, worker_secret, filenames)
            config["local_backgrounds_path"] = str(local_dir)

        status.log(f"Worker started for job #{job_id} (RunPod)")

        def upload_before_completion() -> None:
            # Runs after "Rendering Karaoke Video" finishes but before
            # run_pipeline() marks the job completed — see the docstring
            # on run_pipeline()'s before_completion parameter. If this
            # raises, run_pipeline()'s own exception handling marks the
            # job failed instead of falsely completed.
            status.log("Uploading finished video to the app…")
            _upload_result(job_dir, callback_base_url, worker_secret, job_id)
            status.log("Upload complete")

        exit_code = run_pipeline(PIPELINE, config, status, before_completion=upload_before_completion)

    return {"job_id": job_id, "success": exit_code == 0}


runpod.serverless.start({"handler": handler})
