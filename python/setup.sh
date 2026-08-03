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

echo "Installing CPU-only PyTorch (pass a CUDA index URL instead if you have a supported NVIDIA GPU) ..."
./venv/bin/pip install torch torchaudio --index-url https://download.pytorch.org/whl/cpu

echo "Installing remaining dependencies from requirements.txt (demucs + whisperx + yt-dlp + openai, can take a while) ..."
./venv/bin/pip install -r requirements.txt

echo
echo "Done. In Settings, set 'Python Path' to:"
echo "  $(pwd)/venv/bin/python"
