# Database Schema

Schema source of truth: [`database/migrations/001_create_tables.sql`](../database/migrations/001_create_tables.sql).
All tables use `InnoDB` / `utf8mb4_unicode_ci`.

## `users`

Admin accounts. Single-role model (`admin`/`user`), session-based auth
(`App\Core\Auth`, backed by `App\Models\User`).

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `email` | VARCHAR(190) UNIQUE | login identifier |
| `password_hash` | VARCHAR(255) | `password_hash()` (bcrypt/argon, PHP default) |
| `role` | ENUM('admin','user') | |
| `created_at`, `updated_at` | DATETIME | |

## `jobs`

One row per karaoke generation request — the central table the whole
pipeline revolves around.

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `user_id` | BIGINT UNSIGNED NULL | FK → `users.id`, `ON DELETE SET NULL` |
| `youtube_url` | VARCHAR(500) | as submitted |
| `youtube_video_id` | VARCHAR(32) NULL | extracted via regex, `App\Models\Job::extractYoutubeId()` |
| `title`, `channel`, `thumbnail_url` | VARCHAR/NULL | filled in once the download stage runs |
| `duration_sec` | INT UNSIGNED NULL | |
| `keep_vocals` | TINYINT(1) | 0 = karaoke mode (instrumental audio), 1 = lyric-video mode (full mix) |
| `state` | ENUM | see state list below |
| `progress_percent` | TINYINT UNSIGNED | 0–100 |
| `eta_seconds` | INT UNSIGNED NULL | optional, not currently populated by any stage |
| `error_message` | TEXT NULL | set when `state = 'failed'` |
| `storage_path` | VARCHAR(500) NULL | `storage/jobs/<id>` |
| `pid` | INT UNSIGNED NULL | reserved, not currently populated |
| `created_at`, `updated_at` | DATETIME | |

`state` values, in pipeline order: `queued`, `downloading`,
`extracting_audio`, `separating_vocals`, `extracting_lyrics`,
`synchronizing`, `generating_images`, `waiting_for_user`,
`rendering_video`, `completed`, `failed`. The canonical ordered list with
display labels lives in `App\Models\Job::pipelineStages()`.

## `lyrics`

One row per job (created once the "Synchronizing Lyrics" stage finishes).

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `job_id` | BIGINT UNSIGNED | FK → `jobs.id`, `ON DELETE CASCADE` |
| `source` | ENUM('lrclib','musixmatch','genius','whisperx') | which provider matched, or `whisperx` if none did (full ASR transcription) |
| `raw_text` | MEDIUMTEXT NULL | plain lyrics text |
| `words_json` | JSON | `[{"word": "Hello", "start": 1.23, "end": 1.71}, ...]` — word-level timestamps, always produced via WhisperX forced alignment regardless of text source |
| `created_at`, `updated_at` | DATETIME | |

## `backgrounds`

Three rows per job (created once "Generating AI Backgrounds" finishes).

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `job_id` | BIGINT UNSIGNED | FK → `jobs.id`, `ON DELETE CASCADE` |
| `prompt` | TEXT | the AI-generated image prompt used |
| `image_path` | VARCHAR(500) | filename only (`image1.png` etc.), relative to `storage/jobs/<id>/` |
| `is_selected` | TINYINT(1) | exactly one row per job should be 1 after selection |
| `created_at` | DATETIME | |

## `videos`

One row per job (created once "Rendering Karaoke Video" finishes).

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `job_id` | BIGINT UNSIGNED | FK → `jobs.id`, `ON DELETE CASCADE` |
| `file_path` | VARCHAR(500) | absolute path to `karaoke.mp4` |
| `duration_sec` | INT UNSIGNED NULL | |
| `resolution` | VARCHAR(20) | always `1920x1080` currently |
| `file_size_bytes` | BIGINT UNSIGNED NULL | |
| `created_at` | DATETIME | |

## `settings`

Key/value store backing the Settings page (everything *except* secrets,
which live in `.env` — see [CONFIGURATION.md](CONFIGURATION.md)).

| Column | Type | Notes |
|---|---|---|
| `key` | VARCHAR(100) PK | |
| `value` | TEXT NULL | |
| `updated_at` | DATETIME | |

Seeded keys: `image_model`, `ffmpeg_path`, `ffprobe_path`, `python_path`,
`yt_dlp_path`, `demucs_model`, `vocal_bleed_back_percent`, `whisperx_model`,
`max_video_length_seconds`, `temp_storage_path`.

## `logs`

Structured execution log, queryable per job. Populated from two sources:
PHP writes its own entries directly (`App\Core\Logger`); Python's per-line
`status.json` log output is mirrored in here by
`App\Services\JobService::syncPythonLogs()` on every status poll (each
line becomes one row, deduplicated by comparing against how many
`source = 'python'` rows already exist for that job — see the method's
docblock for why that's safe to call repeatedly without a separate
"already synced" marker).

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `job_id` | BIGINT UNSIGNED NULL | FK → `jobs.id`, `ON DELETE CASCADE`; NULL for app-wide events (e.g. settings changes) |
| `level` | ENUM('info','warning','error') | |
| `source` | ENUM('php','python','ffmpeg','openai') | `ffmpeg`/`openai` values are reserved for future finer-grained tagging — today ffmpeg/OpenAI output is folded into `python`-sourced lines since it's Python that shells out to/calls them |
| `message` | TEXT | |
| `context` | JSON NULL | structured extra data (PHP-side only) |
| `created_at` | DATETIME | |

## Entity relationships

```
users 1───* jobs 1───1 lyrics
                 1───* backgrounds
                 1───1 videos
                 1───* logs
```

## What's *not* in the database

Generated files (downloaded audio, separated stems, background images,
the rendered MP4, the `.ass` subtitle file, and the live `status.json`/
`config.json` handoff files) live on disk in `storage/jobs/<job_id>/`,
referenced by path/filename from the tables above — they are never stored
as BLOBs.
