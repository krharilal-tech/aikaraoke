# Configuration Guide

Two layers of configuration:

- **`.env`** — secrets and per-environment values (database credentials,
  API keys, app URL). Never committed; copy `.env.example` to start.
- **Settings page** (`settings` database table) — everything else, editable
  at runtime without touching files or restarting anything.

## `.env` reference

| Key | Meaning | Set by |
|---|---|---|
| `APP_NAME` | Displayed in the page title / navbar | Install wizard |
| `APP_ENV` | `local` or `production` — informational only | Manual |
| `APP_DEBUG` | `true` shows full exception details on error pages; **set `false` in production** | Manual |
| `APP_URL` | Base URL, used to build every link/asset path | Install wizard |
| `APP_KEY` | Random value generated at install time; not currently used for encryption, reserved | Install wizard |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | MySQL connection | Install wizard |
| `OPENAI_API_KEY` | Required when Background Source is "AI Generated" (see below) | Settings page |
| `MUSIXMATCH_API_KEY` | Optional — enables Musixmatch as a lyrics source | Settings page |
| `GENIUS_ACCESS_TOKEN` | Optional — enables Genius as a lyrics source | Settings page |
| `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI` | Optional — enables the "Continue with Google" sign-in button. Leave `GOOGLE_CLIENT_ID` blank to hide it (email/password still works). See [console.cloud.google.com/apis/credentials](https://console.cloud.google.com/apis/credentials) | Manual |
| `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_ENCRYPTION`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | Optional — SMTP credentials for job-completion emails (see [Email notifications & cron jobs](#email-notifications--cron-jobs) below). Leave `MAIL_HOST` blank to disable sending — jobs still work, notifications are just skipped | Manual |
| `SESSION_NAME`, `SESSION_LIFETIME` | PHP session cookie name/lifetime (seconds) | Manual |

`App\Core\EnvWriter` rewrites individual keys in place without disturbing
comments or unrelated lines, so both the installer and the Settings page
can safely update `.env` piecemeal.

## Settings page reference

All of these live in the `settings` table (`key`/`value`), read through
`App\Models\Setting`, and are snapshotted into each job's `config.json` at
creation time (so changing a setting takes effect for the *next* job you
start, not jobs already in flight):

| Setting | Default | Notes |
|---|---|---|
| OpenAI Image Model | `gpt-image-1` | Anything else is treated as a dall-e-3-style model for size selection — see `services/backgrounds.py::generate_images()`. Only used when Background Source is "AI Generated" |
| OpenAI Prompt Model | `gpt-4o-mini` | Writes the 3 image prompts. Only used when Background Source is "AI Generated" |
| Background Source | `openai` | `openai` = generate 3 original images per job via the OpenAI Images API (costs money, needs `OPENAI_API_KEY`, subject to your OpenAI account's billing limits). `local` = pick 3 images at random from **Local Backgrounds Folder** instead — free, no API key needed, no OpenAI dependency for this stage at all. See [Using local background images](#using-local-background-images) below |
| Local Backgrounds Folder | `storage/backgrounds` | Where to look for images when Background Source is `local`. Accepts `.png`/`.jpg`/`.jpeg`/`.webp` |
| FFmpeg Path | `ffmpeg` | Full path recommended on Windows (no reliable system-wide PATH by default) |
| FFprobe Path | `ffprobe` | Used to probe audio duration before rendering |
| Python Path | `python` | **Should point at `python/venv`'s interpreter**, not a system Python, so the packages installed by `setup.ps1`/`setup.sh` are found |
| yt-dlp Path | `yt-dlp` | Currently informational — the download stage actually uses the `yt-dlp` **Python package** (`import yt_dlp`) inside the worker's venv, not this CLI path, so what matters in practice is that `yt-dlp` is installed into that venv (it's in `requirements.txt`) |
| Demucs Model | `htdemucs` | Passed to `python -m demucs -n <model>` |
| WhisperX Model | `base` | Trade-off: bigger = more accurate transcription but more RAM/time. See [TROUBLESHOOTING.md](TROUBLESHOOTING.md#removing-vocals-or-transcription-fails-with-mkl_malloc-failed-to-allocate-memory) before going above `small` on a constrained machine |
| Transcription Language | `auto` | Only used when WhisperX has to transcribe from scratch (no lyrics provider matched). `auto` restricts detection to the app's 4 supported languages (Tamil/Malayalam/Hindi/English — see `CANDIDATE_LANGUAGES` in `python/services/whisperx_engine.py`) with VAD filtering and multi-segment sampling, rather than an unrestricted guess across the ~99 languages Whisper knows. Pin it to `ta`/`ml`/`hi`/`en` explicitly if you know you're processing a 5th language `auto` isn't scoped to, or want to skip detection entirely. See [Regional-language transcription](#regional-language-transcription-tamil-malayalam-hindi) below |
| Maximum Video Length (seconds) | `600` (10 min) | Enforced in `stages/download.py` — videos longer than this fail the download stage with a clear error rather than silently truncating |
| Temporary Storage Path | `storage/jobs` | Where per-job working directories are created |

## Choosing a WhisperX model

Roughly, from lightest to heaviest: `tiny` < `base` < `small` < `medium` <
`large`. Bigger models transcribe more accurately (especially for
music/singing, which is harder than clean speech) but need proportionally
more RAM and CPU time. `base` is the default specifically because it was
the first tier that ran reliably in the constrained VM this project was
developed and tested in — if you have a beefier machine or a GPU, `small`
or `medium` will give noticeably better lyric accuracy.

## Regional-language transcription (Tamil, Malayalam, Hindi)

`auto` (the default) restricts language detection to Tamil, Malayalam,
Hindi, and English — the app's 4 supported languages — instead of letting
WhisperX guess freely across the ~99 languages it knows. It also applies
voice-activity-detection filtering and samples 3 segments across the audio
(`_detect_restricted_language()` in
`python/services/whisperx_engine.py`), so a long instrumental intro before
the first line is sung doesn't get treated as the only evidence available.
This fixed a real case where a Tamil film song was transcribed entirely as
English.

If you're consistently processing only one language, or a song has
significant content in a 5th language `auto` isn't scoped to, pin
**Transcription Language** to the actual language explicitly instead —
this skips detection entirely.

**Word-level alignment support differs by language** (this only matters
when WhisperX itself did the transcribing — a lyrics-provider match doesn't
need it the same way, since forced alignment still runs on that provider
text regardless of source):

- **Hindi and Malayalam** both have a dedicated wav2vec2 forced-alignment
  model bundled with WhisperX, so word timing is precisely aligned to the
  audio, same as English.
- **Tamil has no such model in WhisperX.** Rather than crash the
  "Synchronizing Lyrics" stage (`services/whisperx_engine.py::align_words()`
  catches this and falls back automatically —
  `_distribute_words_evenly()`), Tamil word timing is *estimated*: each
  transcribed phrase's words are spread evenly across that phrase's known
  start/end window. This still produces usable word-level karaoke
  highlighting, just less tightly synced to the actual sung timing than
  real forced alignment gives you. The job's log will say
  `"No forced-alignment model available for language 'ta'..."` when this
  fallback engages, so it's always visible rather than silent.

**Malayalam transcription uses a different model than the other 3
languages.** Generic multilingual Whisper reliably fails to render actual
Malayalam script even when it correctly identifies the language — tested
at `base`/`small`/`medium`, always wrong. `services/malayalam_asr.py`
transcribes Malayalam with `thennal/whisper-medium-ml`, a Malayalam-
specific fine-tune, instead — `whisperx_engine.py::transcribe()` routes to
it automatically whenever the resolved language is `ml`, no setting to
flip. It's noticeably slower than the generic model on CPU (~12-15x
realtime), so a lyrics-provider match (see [Setting up lyrics
providers](#setting-up-lyrics-providers) below) is still preferable when
one exists — it's both faster and skips ASR uncertainty entirely. See
[TROUBLESHOOTING.md](TROUBLESHOOTING.md#malayalam-lyrics-used-to-come-out-in-the-wrong-script-eg-tamil-characters--fixed)
for the full history.

## Setting up lyrics providers

`LRCLIB` needs no key and is tried first. `MUSIXMATCH_API_KEY` and
`GENIUS_ACCESS_TOKEN` (in `.env`, not the Settings page — they're
secrets, same as `OPENAI_API_KEY`) are optional but worth setting: any
provider match is real lyrics text instead of ASR output, which matters
most for exactly the regional-language cases above. Genius tokens are
free — create an API client at genius.com/api-clients and use its
"Client Access Token", no OAuth flow needed. Musixmatch's free tier
truncates lyrics (handled automatically — see
`services/lyrics_providers.py::fetch_musixmatch()`).

## Choosing an OpenAI image model

`gpt-image-1` and `dall-e-3` are both supported — `generate_images()`
picks a generation size based on which one is configured (`1536x1024` vs
`1792x1024`, the widest 16:9-ish option each supports). Neither model can
generate at exactly 1920×1080, so the render stage's `zoompan` scale step
brings whatever comes back up to the final resolution — this is
deliberate, not a workaround for a bug.

## Using local background images

Set **Background Source** to **Local Folder** to skip OpenAI image
generation entirely — no API key, no per-job cost, and no exposure to
OpenAI account billing limits. Drop `.png`/`.jpg`/`.jpeg`/`.webp` files
into the **Local Backgrounds Folder** (`storage/backgrounds` by default;
see `storage/backgrounds/README.md`).

Each job that uses this mode (`python/stages/generate_images.py` →
`python/services/local_backgrounds.py::pick_images()`) picks 3 images at
random from that folder and copies them into the job's own directory as
`image1.<ext>`/`image2.<ext>`/`image3.<ext>`, preserving each file's
original extension — the first of the 3 is used automatically (no
selection step anymore — see `generate_images.py`), and everything
downstream (the `backgrounds` table, the render stage's Ken Burns scaling)
works identically regardless of which source produced them. If you've only
added 1-2 images, they're reused at random rather than erroring out. If the
folder is empty or doesn't exist, the job fails at that stage with a
message telling you to add an image or switch back to "AI Generated" —
same as any other stage failure, visible on the job's progress page.

Any resolution works; widescreen (16:9-ish) source images crop the least.

## Email notifications & cron jobs

Two things happen on a schedule rather than as part of the request/job
cycle, both via `App\Models\Job` + plain PHP CLI scripts in `bin/` — no
job queue system, just cron:

| Script | Purpose | Suggested schedule |
|---|---|---|
| `bin/sync-jobs.php` | Syncs every still-running (or not-yet-notified) job's `status.json` into the database, and emails the owner once a job reaches `completed`/`failed`. This is what makes email notifications work even if nobody has the progress page open — the normal sync path only runs when a browser is actively polling `GET /api/jobs/{id}/status` | every 1-2 minutes |
| `bin/cleanup-expired-jobs.php` | Deletes the on-disk files (audio, stems, images, the final video) for jobs finished more than 7 days ago, to keep storage from growing forever. The `jobs` database row is kept — only the files disappear, and `expired_at` gets set so the app shows "this video has expired" instead of a broken download link | once daily |

**Setting up SMTP**: fill in `MAIL_HOST`/`MAIL_PORT`/`MAIL_USERNAME`/`MAIL_PASSWORD`/`MAIL_ENCRYPTION`
in `.env` (see the [`.env` reference](#env-reference) above) with credentials
from whatever mailbox/SMTP relay you're using. Until `MAIL_HOST` is filled
in, notification emails are silently skipped (logged instead) — the job
pipeline is never blocked by a missing or failing mail config. Every job
that finished *before* SMTP was ever configured is backfilled as
already-notified (see the migration in
`database/migrations/004_notifications_and_cleanup.sql`), so turning on
SMTP for the first time won't suddenly email users about old jobs — only
jobs that finish afterward trigger a real email.

**Cron syntax** (Linux/Hostinger):

```
*/2 * * * * /usr/bin/php /home/youruser/aikaraoke/bin/sync-jobs.php >> /home/youruser/aikaraoke/storage/logs/cron-sync.log 2>&1
0 3 * * * /usr/bin/php /home/youruser/aikaraoke/bin/cleanup-expired-jobs.php >> /home/youruser/aikaraoke/storage/logs/cron-cleanup.log 2>&1
```

On Windows/WAMP (this project's dev environment), there's no cron — use
Task Scheduler with an action of `"C:\wamp64\bin\php\php8.1.31\php.exe"
C:\wamp64\www\aikaraoke\bin\sync-jobs.php`, or just run these manually
while testing.

## RunPod Serverless integration

By default, `JobService::spawnWorker()` runs `python/worker.py` as a local
subprocess on whatever machine PHP itself is running on (`proc_open()` +
`nohup`/`start /B`) — fine for a VPS, but shared/cloud hosting (Hostinger
Cloud Hosting included) can't run long-lived background processes or
install system-level ML dependencies. Filling in `RUNPOD_API_KEY` and
`RUNPOD_ENDPOINT_ID` in `.env` switches every new job to running instead
on a remote GPU via [RunPod Serverless](https://www.runpod.io/serverless-gpu) —
leave `RUNPOD_API_KEY` blank to keep using local subprocess processing.

**Why this needed more than a config flag**: once processing happens on a
different machine, PHP and Python can no longer share a local disk. Every
piece of `python/worker.py` that assumed "the same filesystem PHP can
see" needed a remote equivalent:

| Local mode | Remote (RunPod) mode |
|---|---|
| PHP writes `config.json` to the job's local folder; Python reads it | PHP sends the same fields as the RunPod job's `input` (`JobService::buildJobConfig()` / `dispatchToRunPod()`) |
| Python writes `status.json` locally via `StatusWriter`; PHP polls that file | Python POSTs the same JSON shape to `POST /api/worker/jobs/{id}/status` via `RemoteStatusWriter` — same interface, so **no stage script had to change** |
| Local backgrounds folder is just `Path.iterdir()` | `handler.py` downloads each file from `GET /api/worker/backgrounds/{filename}` into a temp folder first, so `local_backgrounds.py::pick_images()` still just sees a local folder |
| The rendered video is already at `storage/jobs/{id}/karaoke.mp4` | `handler.py` uploads it to `POST /api/worker/jobs/{id}/upload` before the job is allowed to report "completed" (see `before_completion` in `worker.py::run_pipeline()`) — otherwise the app could tell a user their video is ready before the file exists on Hostinger |

All three `/api/worker/...` endpoints (`WorkerCallbackController`) are
authenticated by a shared secret, not a session — every RunPod job carries
`WORKER_API_SECRET` as part of its `input` and sends it back as an
`X-Worker-Secret` header on every callback. Generate one with:

```
php -r "echo bin2hex(random_bytes(32));"
```

**Deploying the container**: RunPod builds directly from a GitHub repo
(no local Docker needed) — connect your repo under RunPod's console
(Settings → Connections → GitHub), then create a Serverless endpoint
pointing at the `Dockerfile` in the repo root. Two settings matter beyond
the defaults:

- **Execution timeout**: raise this well past the default 10 minutes —
  Malayalam songs alone took 30-50 minutes on CPU in testing; even with a
  GPU's speedup, leave real margin (20-30+ minutes) rather than have a
  slow job get killed mid-render.
- RunPod doesn't auto-deploy on every commit to your default branch —
  pushing a new commit updates what a *new release* would build, but you
  need to actually cut a release for it to reach the live endpoint.

PHP dispatches via RunPod's async `/run` operation (not `/runsync`, whose
result window is far too short for these job lengths) — see
[docs.runpod.io/serverless/endpoints/send-requests](https://docs.runpod.io/serverless/endpoints/send-requests)
for the underlying API this wraps.
