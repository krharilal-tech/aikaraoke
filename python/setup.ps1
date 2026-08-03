# AI Karaoke Maker — Python worker setup (Windows / WAMP)
#
# Creates python/venv and installs everything the worker needs. Run once
# from PowerShell in this directory:
#   cd python
#   .\setup.ps1
#
# FFmpeg itself is a separate binary (not a pip package) — download a
# Windows build from https://www.gyan.dev/ffmpeg/builds/ (the "essentials"
# or "full" build), extract it, and point Settings -> FFmpeg Path /
# FFprobe Path at ffmpeg.exe / ffprobe.exe inside its bin\ folder.

$ErrorActionPreference = "Stop"

Write-Host "Creating virtual environment in python\venv ..."
python -m venv venv

Write-Host "Upgrading pip ..."
& .\venv\Scripts\python.exe -m pip install --upgrade pip

Write-Host "Installing CPU-only PyTorch (add --index-url for a CUDA build instead if you have a supported NVIDIA GPU) ..."
& .\venv\Scripts\pip.exe install torch torchaudio --index-url https://download.pytorch.org/whl/cpu

Write-Host "Installing remaining dependencies from requirements.txt (demucs + whisperx + yt-dlp + openai, can take a while) ..."
& .\venv\Scripts\pip.exe install -r requirements.txt

Write-Host ""
Write-Host "Done. In Settings, set 'Python Path' to:"
Write-Host "  $PWD\venv\Scripts\python.exe"
