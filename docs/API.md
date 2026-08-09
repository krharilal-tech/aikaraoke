# API / Routes Reference

All routes are defined in [`routes/web.php`](../routes/web.php) and
dispatched by `App\Core\Router`. Every route requires an authenticated
session **except** `GET/POST /login` and the standalone `/install/`
wizard (which locks itself once an admin account exists — see
[INSTALLATION.md](INSTALLATION.md)).

Unauthenticated requests get a `302` redirect to `/login?next=<path>` for
normal page loads, or a `401 {"success":false,"message":"Please sign in."}`
JSON body for AJAX requests (detected via `X-Requested-With:
XMLHttpRequest` or an `Accept: application/json` header).

State-changing endpoints (all `POST` routes below) require a valid CSRF
token, sent as either a `_csrf` form field or an `X-CSRF-Token` header.
Get the current token from the `<meta name="csrf-token">` tag on any
authenticated page, or the hidden `_csrf` field on the login form.

## Auth

### `GET /login`
Login form. No auth required.

### `POST /login`
Body: `email`, `password`, `_csrf`, optional `next` (redirect target after
success). Rate-limited to 5 attempts per 5 minutes per IP
(`App\Core\RateLimiter`). Returns `302` to `next` (or `/`) on success, or
re-renders the login form with an error on failure.

### `POST /logout`
Body: `_csrf`. Ends the session, `302` to `/login`.

## Pages

### `GET /`
Landing page (paste-a-URL form).

### `GET /jobs`
"My Videos" — lists all jobs, most recent first.

### `GET /jobs/{id}`
Progress page for one job. Renders the stage list, and (once the job
reaches the relevant state) the background-picker or the video
preview/download UI, driven client-side by `public/assets/js/job-progress.js`
polling the status endpoint below every 2 seconds.

### `GET /settings`
Settings form (tool paths, model names, API keys).

### `POST /settings`
Body: any of `image_model`, `ffmpeg_path`, `ffprobe_path`, `python_path`,
`yt_dlp_path`, `demucs_model`, `vocal_bleed_back_percent`, `whisperx_model`,
`max_video_length_seconds`, `temp_storage_path` (persisted to the
`settings` table), plus optional `OPENAI_API_KEY`, `MUSIXMATCH_API_KEY`,
`GENIUS_ACCESS_TOKEN` (written to `.env`, never the database — blank
means "leave unchanged", there's no way to read a saved key back out
through this endpoint). Requires `_csrf`. `302` back to `/settings` on
success.

## Job pipeline

### `POST /jobs`
Create a new job and start processing. Body: `youtube_url` (required,
must resolve to `youtube.com`/`youtu.be`/`music.youtube.com`),
`keep_vocals` (optional, `1` for lyric-video mode), `_csrf`. Rate-limited
to 10 per 10 minutes per IP.

```json
// 200
{"success": true, "job_id": 42, "max_duration_seconds": 600}
// 422 — invalid/missing URL
{"success": false, "message": "Please provide a valid YouTube video URL."}
```

### `GET /api/jobs/{id}/status`
Polled every 2s by the progress page. Reads `storage/jobs/{id}/status.json`
(written by the Python worker), mirrors any changes into the database, and
returns the merged view:

```json
{
  "success": true,
  "job_id": 42,
  "state": "separating_vocals",
  "progress_percent": 30,
  "eta_seconds": null,
  "error_message": null,
  "title": "Song Title",
  "channel": "Uploader Name",
  "thumbnail_url": "https://i.ytimg.com/...",
  "duration_sec": 214,
  "message": "Removing Vocals…",
  "logs": ["[12:00:01] Worker started", "..."],
  "stages": [{"state": "queued", "label": "Queued"}, "..."],
  "backgrounds": [
    {"id": 1, "image_url": "http://.../jobs/42/image/image1.png", "is_selected": false}
  ],
  "video": null
}
```

`backgrounds` is populated once "Generating AI Backgrounds" finishes;
`video` is populated once rendering finishes
(`{"stream_url", "download_url", "duration_sec", "resolution", "file_size_bytes"}`).

### `POST /jobs/{id}/select-background`
Body: `background_id`, `_csrf`. Only valid while the job is in
`waiting_for_user`. Marks the chosen background selected in the database
and writes `storage/jobs/{id}/selection.json`, which the paused Python
worker (`stages/wait_for_selection.py`) is polling for — this is what
resumes the job into the rendering stage.

```json
// 200
{"success": true}
// 409 — job isn't at the right stage
{"success": false, "message": "This job is not waiting for a background selection."}
// 422 — background_id doesn't belong to this job
{"success": false, "message": "Invalid background selection."}
```

### `GET /jobs/{id}/image/{filename}`
Streams a generated background image (`image1.png`/`image2.png`/`image3.png`)
from `storage/jobs/{id}/`, which is outside the webroot on purpose — this
is the only way the browser reaches those files. Validates the filename
against a `png|jpe?g|webp` extension whitelist and confirms the resolved
path stays inside that job's directory before serving.

### `GET /jobs/{id}/video`
Inline video stream for the `<video>` preview player. Supports HTTP Range
requests (`206 Partial Content` with `Content-Range`) so the player can
seek without downloading the whole file — see
`App\Controllers\JobController::streamVideo()`.

### `GET /jobs/{id}/download`
Same file, `Content-Disposition: attachment` instead of `inline`.

## Not part of the router: `/install/`

`public/install/index.php` is a standalone script (not registered in
`routes/web.php`) so it can run before a database/admin account exists.
See [INSTALLATION.md](INSTALLATION.md) for its steps and the
already-installed lockdown behavior.
