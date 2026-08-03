# Python Worker

This directory contains **only** AI/media processing code — yt-dlp, Demucs,
WhisperX, OpenAI image generation, and FFmpeg rendering. It has no web
server, no routing, and no database driver: PHP (`app/Services/JobService.php`)
spawns `worker.py` as a detached CLI subprocess per job and the two sides
exchange data exclusively through JSON files in `storage/jobs/<job_id>/`:

| File | Written by | Read by |
|---|---|---|
| `config.json` | PHP, once, at job creation | Python, once, at worker startup |
| `status.json` | Python, continuously during the job | PHP, on every `GET /api/jobs/{id}/status` poll |

## Setup

```
cd python
./setup.ps1   # Windows
./setup.sh    # Linux/Ubuntu
```

This creates `python/venv` and installs `requirements.txt` into it. Then, in
the app's **Settings** page, set **Python Path** to the venv's interpreter:

- Windows: `C:\wamp64\www\aikaraoke\python\venv\Scripts\python.exe`
- Ubuntu: `/var/www/aikaraoke/python/venv/bin/python`

FFmpeg is a separate binary dependency (not installable via pip) — see
`setup.ps1`/`setup.sh` for where to get it per platform, then set **FFmpeg
Path** / **FFprobe Path** in Settings.

## Layout

- `worker.py` — dispatcher. Reads `config.json`, runs each pipeline stage in
  order, writes `status.json` after every step.
- `services/status_writer.py` — atomic JSON status-file writer shared by
  every stage.
- `services/lyrics.py`, `services/backgrounds.py`, `services/render.py` —
  reusable logic for the lyrics, AI-background, and video-rendering stages.
- `stages/*.py` — one module per pipeline stage, each exposing a `run(config,
  status)` function that `worker.py` calls in sequence.

## Running a stage manually (debugging)

```
venv\Scripts\python.exe worker.py --job-id 5 --root C:\wamp64\www\aikaraoke
```

This expects `storage/jobs/5/config.json` to already exist (the app creates
it when you submit a YouTube URL) and will write live progress to
`storage/jobs/5/status.json` as it runs.
