# AI Karaoke Maker

Turn any YouTube music video into a professional karaoke video: paste a URL,
and get back a 1920×1080 MP4 with the vocals removed, word-by-word
highlighted lyrics, and an original AI-generated background — no editing
required.

![PHP](https://img.shields.io/badge/PHP-8.1%2B-777bb4)
![MySQL](https://img.shields.io/badge/MySQL-8-4479A1)
![Python](https://img.shields.io/badge/Python-3.11-3776AB)

## How it works

1. **Paste a YouTube URL** and click *Generate Karaoke*.
2. The app downloads the audio, separates vocals from the instrumental
   (Demucs), finds or transcribes the lyrics with word-level timestamps
   (LRCLIB → Musixmatch → Genius → WhisperX), and generates three original
   AI background images (OpenAI Images API) — all visible live on a
   progress page that polls every 2 seconds.
3. **Pick a background** from the three options.
4. FFmpeg renders the final video: a slow Ken Burns pan/zoom over your
   chosen background, with karaoke-style word highlighting (yellow =
   current word, white = upcoming, light gray = already sung) burned in via
   an ASS subtitle track, muxed with the instrumental (or full mix, in
   "keep vocals" lyric-video mode) as H.264/AAC.
5. **Preview and download** the finished MP4 — the built-in player supports
   play/pause/seek (HTTP Range-backed) and fullscreen.

## Architecture

PHP owns everything web-facing — routing, UI, sessions/auth, the job queue,
and the database. Python only runs AI/media subprocesses (yt-dlp, Demucs,
WhisperX, OpenAI Images, FFmpeg), invoked by PHP as a detached CLI process
per job. The two sides never share a database connection — they hand off
exclusively through JSON files in `storage/jobs/<job_id>/`:

```
Browser  <--2s poll-->  PHP (routes, DB, job state)  <--config.json-->  Python worker
                                                        <--status.json--
```

See [docs/DEVELOPER_GUIDE.md](docs/DEVELOPER_GUIDE.md) for the full design,
including the pipeline stage list, database schema, and how the karaoke
subtitle rendering actually works.

## Stack

PHP 8.1+ (hand-rolled MVC, no framework) · Apache · MySQL 8 · Bootstrap 5.3 ·
vanilla JS + jQuery · Python 3.11 (yt-dlp, Demucs, WhisperX, OpenAI SDK) ·
FFmpeg (libx264, libass)

## Quick start

```
git clone <this repo> aikaraoke
cd aikaraoke
composer install
```

Then open `http://localhost/aikaraoke/install/` in a browser and follow the
wizard — it checks your environment, creates the database, writes `.env`,
and creates your admin login. Full instructions (including WAMP-specific
notes and the Python/FFmpeg setup):

- **[docs/INSTALLATION.md](docs/INSTALLATION.md)** — full installation guide
- **[docs/WAMP_SETUP.md](docs/WAMP_SETUP.md)** — WAMP-specific setup
- **[docs/DEPLOYMENT_UBUNTU.md](docs/DEPLOYMENT_UBUNTU.md)** — production deployment on Ubuntu
- **[docs/CONFIGURATION.md](docs/CONFIGURATION.md)** — every setting explained
- **[docs/DATABASE_SCHEMA.md](docs/DATABASE_SCHEMA.md)** — table-by-table schema reference
- **[docs/API.md](docs/API.md)** — every route/endpoint
- **[docs/TROUBLESHOOTING.md](docs/TROUBLESHOOTING.md)** — common problems and fixes
- **[docs/DEVELOPER_GUIDE.md](docs/DEVELOPER_GUIDE.md)** — architecture deep-dive

## Requirements

- PHP 8.1+ with `pdo_mysql`, `mbstring`, `json`, `fileinfo`
- MySQL 8 (or MariaDB 10.4+)
- Apache with `mod_rewrite`
- Python 3.10+
- FFmpeg (with `libx264` and `libass` support)
- An OpenAI API key (for background image generation)

## License

Proprietary — see your engagement terms. Generated backgrounds are original
AI art (mood-based scenes, never artist likenesses or album art); you are
responsible for your own rights clearance on the source audio/video you
process.
