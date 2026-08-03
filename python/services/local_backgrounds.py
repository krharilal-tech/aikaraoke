"""
Picks background images from a user-managed local folder instead of
generating them with the OpenAI Images API — see
storage/backgrounds/README.md. Used when Settings -> Background Source is
"Local Folder" instead of "AI Generated".
"""

from __future__ import annotations

import random
from pathlib import Path
from typing import Any

VALID_EXTENSIONS = {".png", ".jpg", ".jpeg", ".webp"}


class LocalBackgroundsError(Exception):
    pass


def pick_images(folder: str, job_dir: Path, count: int = 3) -> list[dict[str, Any]]:
    source_dir = Path(folder)

    if not source_dir.is_dir():
        raise LocalBackgroundsError(
            f"Local backgrounds folder not found: {source_dir}. Create it and add at "
            "least one image, or switch Background Source back to 'AI Generated' in Settings."
        )

    candidates = sorted(
        p for p in source_dir.iterdir() if p.is_file() and p.suffix.lower() in VALID_EXTENSIONS
    )

    if not candidates:
        raise LocalBackgroundsError(
            f"No image files found in {source_dir}. Add at least one .png/.jpg/.webp file, "
            "or switch Background Source back to 'AI Generated' in Settings."
        )

    if len(candidates) >= count:
        chosen = random.sample(candidates, count)
    else:
        # Fewer images on disk than needed — reuse some at random so the
        # selection step still offers `count` options rather than erroring
        # out over what's really just a "you only added 1-2 images" case.
        chosen = [random.choice(candidates) for _ in range(count)]

    results: list[dict[str, Any]] = []

    for index, source_path in enumerate(chosen, start=1):
        filename = f"image{index}{source_path.suffix.lower()}"
        (job_dir / filename).write_bytes(source_path.read_bytes())
        results.append({"prompt": f"Local image: {source_path.name}", "image_path": filename})

    return results
