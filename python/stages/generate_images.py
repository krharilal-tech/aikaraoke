"""
Stage: Generating AI Backgrounds.

There used to be a separate "Waiting for Background Selection" stage after
this one that paused the whole job until a human picked one of the
candidates in the browser. That's gone now — this stage picks the first
candidate itself and writes selection.json immediately, so rendering can
start right away with no pause.
"""

from __future__ import annotations

import json
from pathlib import Path
from typing import Any

from services.backgrounds import generate_images
from services.local_backgrounds import pick_images
from services.status_writer import StatusWriter


def run(config: dict[str, Any], status: StatusWriter) -> dict[str, Any]:
    job_dir = Path(config["job_dir"])

    if config.get("background_source") == "local":
        status.log("Background Source is set to 'Local Folder' — picking from disk instead of calling OpenAI")
        backgrounds = pick_images(config.get("local_backgrounds_path", ""), job_dir)
        status.log(f"Selected {len(backgrounds)} local image(s): " + ", ".join(b["image_path"] for b in backgrounds))
    else:
        metadata = status.snapshot().get("metadata") or {}
        title = metadata.get("title") or "Untitled"

        lyrics_path = job_dir / "lyrics_raw.json"
        lyrics_text = ""

        if lyrics_path.exists():
            lyrics_text = json.loads(lyrics_path.read_text(encoding="utf-8")).get("text", "")

        backgrounds = generate_images(config, status, title, lyrics_text)

    status.update(backgrounds=backgrounds)

    chosen = backgrounds[0]
    (job_dir / "selection.json").write_text(
        json.dumps({"image_path": chosen["image_path"]}, indent=2),
        encoding="utf-8",
    )
    status.log(f"Automatically selected background: {chosen['image_path']}")

    return {"backgrounds": backgrounds}
