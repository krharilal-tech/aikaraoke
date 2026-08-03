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

# requirements.txt pins an exact torch version (confirmed compatible with
# whisperx/pyannote-audio's VAD checkpoint loading — a version mismatch
# here previously broke that on RunPod) rather than leaving it to a
# separate pre-install step.
Write-Host "Installing dependencies from requirements.txt (torch + demucs + whisperx + yt-dlp + openai, can take a while) ..."
& .\venv\Scripts\pip.exe install -r requirements.txt

Write-Host ""
Write-Host "Done. In Settings, set 'Python Path' to:"
Write-Host "  $PWD\venv\Scripts\python.exe"
