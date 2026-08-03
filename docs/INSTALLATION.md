# Installation Guide

This covers installing AI Karaoke Maker on any PHP 8.1+ / MySQL 8 / Apache
stack. For WAMP-specific notes (PHP version switching, `.htaccess`
quirks on Windows) see [WAMP_SETUP.md](WAMP_SETUP.md). For a production
Ubuntu server see [DEPLOYMENT_UBUNTU.md](DEPLOYMENT_UBUNTU.md).

## 1. Get the code onto your server

Place the project so that `public/` can become (or be proxied to) a web
root — e.g. `C:\wamp64\www\aikaraoke` on WAMP, or `/var/www/aikaraoke` on
Ubuntu.

## 2. Install PHP dependencies

```
composer install
```

This project has **zero runtime Composer dependencies** by design (the
whole PHP side is hand-rolled — see [DEVELOPER_GUIDE.md](DEVELOPER_GUIDE.md))
so this step just generates the PSR-4 autoloader.

## 3. Point Apache at the app

Two options:

- **Quick start (shared www folder, e.g. WAMP default)**: drop the project
  into `www/aikaraoke` as-is. The root `.htaccess` forwards every request
  into `public/` automatically. Visit `http://localhost/aikaraoke/`.
- **Production (recommended)**: point the Apache `DocumentRoot` (or a
  `VirtualHost`) directly at the `public/` folder, and delete the root
  `.htaccess` (it's only needed for the shared-folder trick). Visit
  `http://your-domain/`.

Either way, `mod_rewrite` and `AllowOverride All` (or at least `FileInfo`)
must be enabled for the directory `.htaccess` files to take effect.

## 4. Run the install wizard

Visit `/install/` in a browser (e.g. `http://localhost/aikaraoke/install/`).
It walks through three steps:

1. **Environment checks** — PHP version, required extensions, `storage/`
   and `.env` writability. Python/FFmpeg/Demucs/WhisperX are checked too
   but are only *warnings* here — they're not needed until step 6 below.
2. **Application & database** — app name/URL, MySQL connection details, and
   your admin email/password. Submitting this step creates the database
   (if it doesn't exist), runs the schema migration, writes `.env`, and
   creates your admin account.
3. **Done** — sign in.

The wizard **locks itself down permanently** the moment an admin account
exists (it checks `SELECT COUNT(*) FROM users` on every request) — visiting
`/install/` again after that shows an "Already Installed" page instead of
letting anyone re-run it. This is deliberate: re-running the wizard could
otherwise let an attacker who finds the URL create a second admin account
or repoint the database.

If you ever *do* need to reinstall, drop the database and delete `.env`
first, or manually `DELETE FROM users;` if you just want to redo step 2
without touching the schema.

## 5. Sign in and add your OpenAI key

Sign in with the admin account you just created, then open **Settings**
and paste your OpenAI API key (used for background image generation).
Musixmatch/Genius API keys are optional — lyrics still work without them
(LRCLIB has no key requirement, and WhisperX transcription is the automatic
fallback when no lyrics provider has a match).

## 6. Install the Python worker environment

Nothing generates until this step is done — it's what actually runs
yt-dlp/Demucs/WhisperX/FFmpeg.

```
cd python
./setup.ps1     # Windows
./setup.sh      # Linux/Ubuntu
```

This creates `python/venv` and installs everything in `requirements.txt`
(CPU-only PyTorch by default — edit the setup script if you have a CUDA
GPU and want the accelerated build). It does **not** install FFmpeg (that's
a separate binary, not a pip package) — see the comment at the top of
`setup.ps1`/`setup.sh` for where to get it per platform.

Once both are installed, go back to **Settings** and set:

- **Python Path** → the venv's interpreter (e.g.
  `C:\wamp64\www\aikaraoke\python\venv\Scripts\python.exe` or
  `/var/www/aikaraoke/python/venv/bin/python`)
- **FFmpeg Path** / **FFprobe Path** → wherever you installed FFmpeg

## 7. Generate your first video

Paste a YouTube URL on the home page. Expect the first run to be slower
than usual — WhisperX downloads its model weights on first use (cached
after that). See [TROUBLESHOOTING.md](TROUBLESHOOTING.md) if a stage fails.

## Verifying the install

- `GET /` should show the landing page (redirects to `/login` first if
  you're not signed in).
- `GET /settings` should show your saved values.
- Creating a job and watching `/jobs/{id}` should show live progress
  through: Queued → Downloading → Extracting Audio → Removing Vocals →
  Extracting Lyrics → Synchronizing Lyrics → Generating AI Backgrounds →
  Waiting for Background Selection → Rendering Karaoke Video → Completed.
