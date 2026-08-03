# Developer Guide

Architecture deep-dive for anyone extending this codebase.

## Directory layout

```
app/
  Config/        plain PHP files returning arrays (app.php, database.php, paths.php)
  Core/          the hand-rolled micro-framework (Router, Model, Database, Auth, ...)
  Controllers/   one per resource (Home, Job, Settings, Auth)
  Models/        thin ActiveRecord-ish wrappers over App\Core\Model
  Services/      business logic that doesn't belong in a controller (JobService)
  Views/         plain PHP templates, no template engine
public/
  index.php      front controller
  install/       standalone install wizard (not part of the router)
  assets/        CSS/JS, and vendored Bootstrap/jQuery/Bootstrap Icons (no CDN)
python/
  worker.py      per-job dispatcher, invoked by PHP as a detached CLI process
  stages/        one module per pipeline stage
  services/      reusable logic shared across stages
  venv/          created by setup.ps1/setup.sh, gitignored
database/
  migrations/    001_create_tables.sql — the whole schema, run by the installer
storage/
  jobs/{id}/     everything for one job: config.json, status.json, media files
  logs/          app.log (PHP-side file log, mirrors what's in the `logs` table)
routes/
  web.php        the entire route table
```

## Request lifecycle (PHP)

1. `public/index.php` boots (`App\Core\Application::boot()`): loads
   `.env`, sets error handling, starts the session, sends security headers.
2. It computes the app's base path from `SCRIPT_NAME` (works whether
   you're served via the root-`.htaccess` forwarding trick or a vhost
   pointed straight at `public/` — see the comment in `public/index.php`)
   and constructs `App\Core\Router` with it.
3. `routes/web.php` registers every route, each with a `bool $auth = true`
   flag (only `/login` and its `POST` are `auth: false`).
4. `Router::dispatch()` matches the path/method, checks
   `App\Core\Auth::check()` if the route requires it (redirecting to
   `/login`, or returning `401` JSON for AJAX requests), then instantiates
   the controller and calls the matched method.
5. Controllers extend `App\Core\Controller`, which provides `view()`,
   `json()`, `redirect()`, `requireCsrf()`, `rateLimit()` helpers.

There's no dependency-injection container — controllers `new` up the
services they need directly (see `JobController::__construct()`). This is
intentional: the app is small enough that a container would add ceremony
without real benefit. If it grows, that's the first thing to reconsider.

## The PHP ↔ Python contract

This is the part most worth understanding before touching either side.
**Python never opens a database connection and never reads `.env`.**
Everything it needs is snapshotted into one file per job:

- `App\Services\JobService::create()` writes
  `storage/jobs/{id}/config.json` — youtube URL, `keep_vocals`, and every
  tool path/model name/API key the job will need, read once at worker
  startup (`worker.py::load_config()`).
- `App\Services\JobService::spawnWorker()` launches
  `python worker.py --job-id {id} --root {path}` detached (`start /B` on
  Windows, `nohup ... &` on Linux) so the HTTP request returns
  immediately.
- The worker writes `storage/jobs/{id}/status.json` after every stage
  (`python/services/status_writer.py` — atomic write-then-`os.replace()`
  so a concurrent PHP read never sees a half-written file).
- `GET /api/jobs/{id}/status` (polled every 2s) reads that file and calls
  `JobService::syncFromFileStatus()`, which:
  - updates `jobs.state`/`progress_percent`/`error_message`/metadata,
  - mirrors new `logs` array entries into the `logs` table (delta-only —
    see `syncPythonLogs()`'s docblock for how it stays idempotent without
    a separate cursor/marker),
  - inserts `lyrics`/`backgrounds`/`videos` rows the first time their
    corresponding `status.json` field appears.
- The "Waiting for Background Selection" stage is the one genuine pause:
  `stages/wait_for_selection.py` polls for
  `storage/jobs/{id}/selection.json` to appear (with a 1-hour timeout).
  `POST /jobs/{id}/select-background` is what writes that file — that's
  the entire resume mechanism, no signals/IPC beyond the filesystem.

If you add a new pipeline stage, follow this pattern: read whatever the
previous stage left in the job directory, do the work, write your own
output file(s), and — if PHP needs to know about the result — add a key to
the `status.update(...)` call and a matching sync branch in
`JobService::syncFromFileStatus()`.

## Pipeline stages

Defined once, in two places that must stay in sync:

- `App\Models\Job::pipelineStages()` — PHP's ordered `(state, label)` list,
  used to render the stage list UI.
- `worker.py`'s `PIPELINE` (states + progress-percent ranges) and
  `STAGE_HANDLERS` (state → the stage module's `run(config, status)`
  function) constants.

| State | Stage module | What it does |
|---|---|---|
| `downloading` | `stages/download.py` | yt-dlp metadata + best-audio download |
| `extracting_audio` | `stages/extract_audio.py` | ffmpeg → normalized `audio.wav` |
| `separating_vocals` | `stages/separate_vocals.py` | Demucs → `vocals.wav` / `instrumental.wav` |
| `extracting_lyrics` | `stages/extract_lyrics.py` | LRCLIB → Musixmatch → Genius → WhisperX ASR fallback (language pinned via Settings -> Transcription Language, or auto-detected) |
| `synchronizing` | `stages/synchronize.py` | WhisperX forced alignment → word-level timestamps, with a same-segment even-spread fallback for languages with no alignment model (Tamil) — see [CONFIGURATION.md](CONFIGURATION.md#regional-language-transcription-tamil-malayalam-hindi) |
| `generating_images` | `stages/generate_images.py` | OpenAI prompt generation + Images API, or `services/local_backgrounds.py` picking from disk when Background Source is "Local Folder" (see [CONFIGURATION.md](CONFIGURATION.md#using-local-background-images)) |
| `waiting_for_user` | `stages/wait_for_selection.py` | polls for `selection.json` |
| `rendering_video` | `stages/render_video.py` | ASS subtitle build + ffmpeg Ken Burns render |

## How the karaoke subtitle rendering actually works

This is the trickiest piece, so it's worth spelling out
(`python/services/ass_builder.py` + `python/services/render.py`):

1. Word-level JSON (`[{"word", "start", "end"}, ...]`) has no line breaks —
   `group_words_into_lines()` infers them from singing pauses (a gap
   >0.7s between words) and a max-words/max-duration cap, since neither
   lyrics providers nor WhisperX give us real line boundaries.
2. For each line, one ASS `Dialogue` event is emitted **per word**,
   spanning that word's `[start, end)` (or through to the next word's
   start, to avoid gaps). Each event's text is the *entire line*, with
   inline `\c&HBBGGRR&` color override tags splitting it into
   already-sung (light gray) / currently-singing (yellow, with a brief
   `\t(...)` scale-pulse) / upcoming (white) spans — that's what produces
   the "sweep" effect across a static line of text rather than
   words popping in individually.
3. The style block sets a black `OutlineColour` + `Shadow` so text stays
   readable over any background.
4. `render.py` burns this in via ffmpeg's `subtitles=` filter (needs
   `libass`), composited over a `zoompan`-based Ken Burns pan/zoom that
   runs continuously across the *entire* video duration (not looped —
   `zoompan`'s `d` parameter is set to the total frame count) on the
   selected background image, upscaled first so the slow zoom doesn't
   just magnify JPEG/PNG compression artifacts.
5. Output is muxed with `instrumental.wav` (default) or `audio.wav` (the
   original full mix, when `keep_vocals` was set) as H.264/AAC, `-crf 20`,
   1920×1080 @ 30fps.

## Adding a new Settings field

1. Add the column/key to the `settings` INSERT block in
   `database/migrations/001_create_tables.sql` (for a new default) *and*
   to `App\Models\Setting::DEFAULTS` (fallback if the DB row is missing).
2. Add it to `SettingsController::DB_KEYS` (or `ENV_KEYS` if it's a
   secret) and to the form in `app/Views/settings/index.php`.
3. If Python needs it, add it to
   `JobService::writeConfigFile()`'s `$config` array.

## Testing philosophy used while building this

There's no automated test suite (not asked for) — instead, every phase of
the build was verified against the *real* stack: actual WAMP/Apache/MySQL,
actual yt-dlp downloads, actual Demucs/WhisperX/FFmpeg runs, and actual
HTTP requests via `curl` (including CSRF-token round-trips, HTTP Range
requests, and path-traversal attempts). If you're extending this project,
the fastest way to gain confidence in a change is the same: run a real job
through `/jobs/{id}` and watch `storage/jobs/{id}/status.json` and the
`logs` table, rather than mocking the pipeline.
