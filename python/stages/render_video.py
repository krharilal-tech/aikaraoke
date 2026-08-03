"""Stage: Rendering Karaoke Video."""

from __future__ import annotations

from typing import Any

from services.render import run as render_run
from services.status_writer import StatusWriter


def run(config: dict[str, Any], status: StatusWriter) -> dict[str, Any]:
    result = render_run(config, status)

    status.update(video={
        "file_path": result["video_path"],
        "duration_sec": result["duration_sec"],
        "resolution": result["resolution"],
        "file_size_bytes": result["file_size_bytes"],
    })

    return result
