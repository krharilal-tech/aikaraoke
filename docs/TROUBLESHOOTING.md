# Troubleshooting Guide

Real problems hit (and fixed) while building and testing this app, plus
how to diagnose them yourself.

## Where to look first

1. **The job's log console** on `/jobs/{id}` — live tail of everything the
   Python worker logged for that job.
2. **`storage/jobs/{id}/status.json`** — the raw file the log console reads
   from; check `error` and the last few `logs` entries.
3. **`storage/jobs/{id}/worker.log`** — raw stdout/stderr of the Python
   process itself, including anything that crashed *before* it could write
   to `status.json` (e.g. a missing `config.json`).
4. **The `logs` MySQL table** — `SELECT * FROM logs WHERE job_id = ? ORDER
   BY created_at`. Same content as the log console, persisted permanently
   (see [DATABASE_SCHEMA.md](DATABASE_SCHEMA.md#logs)).
5. **`storage/logs/app.log`** — PHP-side errors (uncaught exceptions,
   pre-database-connection failures).

## "The job is stuck at 0% / Queued forever"

The Python worker never started. Check:

- **Settings → Python Path** actually points at a working interpreter —
  test it directly: `<python_path> --version`.
- On Windows, confirm `worker.log` exists in the job's folder at all. If
  it's empty or missing, the `cmd /c start /B` spawn itself failed — see
  [WAMP_SETUP.md](WAMP_SETUP.md#7-windows-specific-process-spawning). A
  classic symptom is `worker.log` containing exactly `The filename,
  directory name, or volume label syntax is incorrect` — that specific
  message means the outer `proc_open()` call double-wrapped the command in
  `cmd /c "..."` quoting; if you've modified `JobService::spawnWorker()`,
  check `bypass_shell` is still `true` there.

## "Job fails immediately with `config.json not found`"

The job directory (`storage/jobs/{id}/`) was deleted or never created.
Confirm `storage/` is writable by the web server user — the install
wizard's step 1 checks this, but permissions can drift after deployment
(see [DEPLOYMENT_UBUNTU.md](DEPLOYMENT_UBUNTU.md#4-file-permissions)).

## "Removing Vocals" fails with `ModuleNotFoundError: No module named 'numpy'`

Seen on a fresh venv where `pip install demucs` didn't pull `numpy` in as
a transitive dependency on some platforms. Fix:

```
<venv>/bin/pip install numpy
```

`python/requirements.txt` pins `numpy` explicitly now specifically because
of this, so a `pip install -r requirements.txt` run should avoid it going
forward — this only bites you if packages were installed manually/out of
order.

## "Removing Vocals" or transcription fails with `mkl_malloc: failed to allocate memory`

The ML model (Demucs or WhisperX) couldn't get enough RAM. This is a
genuine resource limit, not a bug — on a constrained/shared machine:

- Switch **Settings → WhisperX Model** to a smaller model (`base` or
  `small` instead of `medium`/`large`) — model size scales roughly with
  memory footprint.
- Close other heavy processes (this exact error showed up during
  development on an ~8GB VM also running MySQL + Apache + an IDE — free
  memory matters more than total memory).
- Check actual free memory: `Get-CimInstance Win32_OperatingSystem |
  Select FreePhysicalMemory` (PowerShell) or `free -h` (Linux).

## Lyrics/transcription stage fails with `[WinError 2] The system cannot find the file specified`

WhisperX's internal audio loader shells out to a bare `ffmpeg` command —
it does **not** use your configured FFmpeg path, it relies on `ffmpeg`
being resolvable on `PATH`. `worker.py` works around this automatically
(`put_ffmpeg_on_path()` prepends your configured FFmpeg's directory to the
process's `PATH` at startup) — if you see this error, confirm **Settings →
FFmpeg Path** is actually set to a valid, existing `ffmpeg.exe`/`ffmpeg`
path; an empty or wrong path means there's nothing valid to prepend.

## "Generating AI Backgrounds" fails with `OpenAI API key is not configured`

Add your key on the **Settings** page. This is the expected, correct
failure mode with no key set — not a bug (verified deliberately during
development by running a job through to this stage without a key
configured, before a key was available to test the real API call).

## Transcribed lyrics are in the wrong language, or nonsensical

`auto` no longer uses WhisperX's raw, unrestricted language detection —
that scans the first ~30 seconds of audio and picks its single best guess
out of the ~99 languages Whisper knows, which misfired for real on a Tamil
film song with an instrumental intro (detected as English). `auto` now
restricts detection to only the app's 4 supported languages
(Tamil/Malayalam/Hindi/English — see `CANDIDATE_LANGUAGES` in
`python/services/whisperx_engine.py`), with voice-activity-detection
filtering and 3-segment sampling so an instrumental intro doesn't dominate
the result. If you still see the wrong language after this fix (e.g. a
song with significant dialogue in a 5th language, or genuinely ambiguous
vocals), set **Settings → Transcription Language** to the actual language
explicitly instead of leaving it on `auto`.

## Malayalam lyrics used to come out in the wrong script (e.g. Tamil characters) — fixed

Generic multilingual Whisper (tested at `base`, `small`, and `medium`)
reliably failed to render actual Malayalam script even when it correctly
identified the language as `ml` — a real case (Avial's "Kanchimmiyo")
decoded to Tamil script, then a garbled Latin/Devanagari mix, then
Tamil/Devanagari with a hallucinated repeated-token loop, never real
Malayalam script, at any size. Whisper has very little Malayalam training
data, so even when the language tag comes back right, the model's output
script collapses into whatever it's more confident in.

**Fixed**: `services/malayalam_asr.py` transcribes Malayalam using
`thennal/whisper-medium-ml`, a community fine-tune of Whisper Medium
trained specifically on Malayalam speech corpora (~11.5% WER on Common
Voice), instead of the generic model. `services/whisperx_engine.py`'s
`transcribe()` routes to it automatically whenever the resolved language
(forced or auto-detected) is `ml` — no settings change needed. Verified
against two real jobs end-to-end (correct Malayalam script, real
forced-alignment word timing, playable rendered video).

Trade-off: this checkpoint runs ~12-15x slower than realtime on CPU (a
~2.5 minute song took ~32 minutes to transcribe in testing) — noticeably
slower than the generic model, but the generic model's output was unusable
for Malayalam regardless of speed. A lyrics-provider match (LRCLIB/
Musixmatch/Genius — see [Setting up lyrics
providers](CONFIGURATION.md#setting-up-lyrics-providers)) still skips ASR
entirely when available and is both faster and more accurate.

## "Synchronizing Lyrics" produces looser word timing than expected

Check the job's log for a line like `No forced-alignment model available
for language 'ta'...`. WhisperX ships a real word-level forced-alignment
model for Hindi and Malayalam, but not Tamil — Tamil word timestamps are
estimated instead (spread evenly across each transcribed phrase's known
start/end) rather than precisely aligned. This is a deliberate fallback,
not a bug: the alternative would be crashing the whole job. See
[CONFIGURATION.md](CONFIGURATION.md#regional-language-transcription-tamil-malayalam-hindi)
for the full explanation.

## Karaoke text doesn't appear / video has no subtitles

- Confirm your FFmpeg build actually has `libass`:
  `ffmpeg -version` and look for `--enable-libass` in the config line. The
  "essentials" Windows builds from gyan.dev include it; some minimal Linux
  distro packages don't — you may need a static FFmpeg build instead.
- Check `storage/jobs/{id}/karaoke.ass` was actually generated and isn't
  empty — an empty file usually means `lyrics_words.json` had zero words
  (e.g. a fully-instrumental track with nothing to transcribe).

## Video preview won't seek / scrub

The `<video>` player relies on HTTP Range support from
`GET /jobs/{id}/video`. If you've put a reverse proxy in front of Apache,
confirm it passes `Range` request headers through and doesn't buffer/strip
`206 Partial Content` responses.

## Install wizard shows "Already Installed" but I need to reinstall

This is deliberate lockdown behavior once an admin account exists — see
[INSTALLATION.md](INSTALLATION.md#4-run-the-install-wizard). To
genuinely start over: `DELETE FROM users;` (to redo just the admin
account) or drop and recreate the whole database plus delete `.env` (to
redo everything).

## CSRF errors ("Invalid or missing CSRF token") on a page left open a long time

The session cookie or CSRF token can go stale if `.env`'s
`SESSION_LIFETIME` is short and the tab was idle past it. Refresh the page
to get a fresh token before retrying the action.
