#!/usr/bin/env bash
# AI Karaoke Maker — Python worker setup (Ubuntu / production)
#
# Creates python/venv and installs everything the worker needs. Run once
# from this directory:
#   cd python
#   ./setup.sh
#
# Requires system ffmpeg (apt install ffmpeg) — see docs/DEPLOYMENT_UBUNTU.md.

set -euo pipefail

echo "Creating virtual environment in python/venv ..."
python3 -m venv venv

echo "Upgrading pip ..."
./venv/bin/python -m pip install --upgrade pip

# requirements.txt pins an exact torch version (confirmed compatible with
# whisperx/pyannote-audio's VAD checkpoint loading — a version mismatch
# here previously broke that on RunPod) rather than leaving it to a
# separate pre-install step. On Linux, PyPI's default torch wheel bundles
# its own CUDA runtime regardless of whether this machine has a GPU, so a
# CPU-only Ubuntu deployment will pull a larger download than strictly
# necessary — harmless, just not the smallest possible install.
echo "Installing dependencies from requirements.txt (torch + demucs + whisperx + yt-dlp + openai, can take a while) ..."
./venv/bin/pip install -r requirements.txt

echo
echo "Done. In Settings, set 'Python Path' to:"
echo "  $(pwd)/venv/bin/python"
