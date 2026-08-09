"""
Stage: Removing Vocals (stem separation via Demucs).

Runs Demucs' two-stem mode (vocals vs. everything else) on the normalized
audio and produces vocals.wav / instrumental.wav in the job directory.
instrumental.wav is what the karaoke renderer uses by default; vocals.wav is
kept so "keep original vocals" (lyric-video mode) can use it instead.
"""

from __future__ import annotations

import shutil
import subprocess
import sys
from pathlib import Path
from typing import Any

from services.ffmpeg_utils import run_ffmpeg
from services.status_writer import StatusWriter
from services.whisperx_engine import select_device

# Demucs' vocal-isolation model was trained mostly on Western pop/rock
# stems, and can misclassify reedy wind instruments with a voice-like
# timbre (e.g. nadaswaram, shehnai) as "vocals" rather than "other" — so
# they vanish from the instrumental track entirely, not just get quieter,
# when that stem is dropped. Blending a small fraction of the removed
# vocals stem back in recovers that misclassified content. Traded off
# against this: the actual singing becomes audible under the instrumental
# too, at whatever fraction is blended back in. Configurable via the
# admin Settings page ("Vocal Bleed-back %"); defaults to 0 (disabled).
DEFAULT_VOCAL_BLEED_BACK_PERCENT = 0


class SeparationError(Exception):
    pass


def run(config: dict[str, Any], status: StatusWriter) -> dict[str, Any]:
    job_dir = Path(config["job_dir"])
    input_path = job_dir / "audio.wav"

    if not input_path.exists():
        raise FileNotFoundError(f"Expected extracted audio at {input_path}, but it doesn't exist.")

    model = config.get("demucs_model") or "htdemucs"
    output_dir = job_dir / "demucs_output"
    device = select_device()

    # Invoked as "<venv python> -m demucs" (not a standalone "demucs" binary)
    # so it always resolves against the interpreter this worker itself is
    # running under, i.e. the venv where requirements.txt was installed.
    command = [
        sys.executable,
        "-m", "demucs",
        "-n", model,
        "--two-stems", "vocals",
        "--device", device,
        "-o", str(output_dir),
        str(input_path),
    ]

    status.log(f"Running Demucs (model={model}, device={device})…")

    result = subprocess.run(command, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)

    if result.returncode != 0:
        tail = "\n".join(result.stderr.strip().splitlines()[-20:])
        raise SeparationError(f"Demucs failed (exit {result.returncode}): {tail}")

    track_dir = output_dir / model / input_path.stem
    vocals_src = track_dir / "vocals.wav"
    instrumental_src = track_dir / "no_vocals.wav"

    if not vocals_src.exists() or not instrumental_src.exists():
        raise SeparationError(f"Demucs finished but expected output files were not found in {track_dir}")

    vocals_dst = job_dir / "vocals.wav"
    instrumental_dst = job_dir / "instrumental.wav"

    shutil.move(str(vocals_src), str(vocals_dst))
    shutil.move(str(instrumental_src), str(instrumental_dst))
    shutil.rmtree(output_dir, ignore_errors=True)

    status.log("Vocal separation complete: vocals.wav / instrumental.wav")

    bleed_back_percent = config.get("vocal_bleed_back_percent", DEFAULT_VOCAL_BLEED_BACK_PERCENT)
    bleed_back_ratio = max(0, min(100, int(bleed_back_percent))) / 100

    if bleed_back_ratio <= 0:
        status.log("Vocal bleed-back disabled (0%) — using the instrumental track as-is")
        return {"vocals_path": str(vocals_dst), "instrumental_path": str(instrumental_dst)}

    status.log(
        f"Blending {int(bleed_back_ratio * 100)}% of the vocals stem back into the "
        "instrumental track — recovers instruments Demucs sometimes misclassifies as vocals "
        "(e.g. nadaswaram), at the cost of the original vocals becoming audible underneath"
    )

    blended_path = job_dir / "instrumental_blended.wav"
    run_ffmpeg(
        [
            "-i", str(instrumental_dst),
            "-i", str(vocals_dst),
            "-filter_complex",
            f"[1:a]volume={bleed_back_ratio}[bleed];[0:a][bleed]amix=inputs=2:duration=first:dropout_transition=0:normalize=0",
            str(blended_path),
        ],
        config,
        status,
        log_label="vocal bleed-back mix",
    )
    blended_path.replace(instrumental_dst)

    return {"vocals_path": str(vocals_dst), "instrumental_path": str(instrumental_dst)}
