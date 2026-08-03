# WAMP Setup Guide

Notes specific to running this app on WAMP (WampServer) on Windows, based on
what actually needed fixing during development.

## 1. Pick a PHP version

This app targets **PHP 8.1+**. WAMP ships multiple PHP versions side by
side — switch the active one from the WAMP tray icon:
**PHP → Version → 8.1.x** (or newer). Confirm with:

```
"C:\wamp64\bin\php\php8.1.31\php.exe" -v
```

If your WAMP only has older versions installed, use WAMP's "Add a PHP
version" feature to download 8.1+, or upgrade WampServer itself.

## 2. Pick a MySQL version

MySQL 8.x is required (for `JSON` columns and modern auth). Switch via the
tray icon: **MySQL → Version → 8.x**.

## 3. Important: WAMP uses a *separate* php.ini for Apache

This tripped up development directly: WampServer runs PHP under Apache via
`mod_fcgid`, which loads its own `php.ini` at
`wamp64\bin\apache\apache<version>\bin\php.ini` — **not** the one in
`wamp64\bin\php\php8.1.31\php.ini`. If an extension works on the CLI but
not through the browser (or vice versa), you're editing the wrong file.
Check which one is actually loaded with a one-line script:

```php
<?php echo php_ini_loaded_file();
```

Required extensions (`pdo_mysql`, `mbstring`, `json`, `fileinfo`) are
enabled by default in a standard WAMP install. This app does **not**
require the `curl` PHP extension — all outbound HTTP calls (yt-dlp,
lyrics APIs, OpenAI) happen from the Python worker, not PHP, so a broken
`php_curl.dll` (a known WAMP/Windows DLL-mismatch issue on some setups)
does not block this app.

## 4. mod_rewrite and AllowOverride

Both must be on for the `.htaccess` files to work:

- `LoadModule rewrite_module modules/mod_rewrite.so` uncommented in
  `httpd.conf` (on by default in recent WampServer).
- `AllowOverride All` for the `www/` directory block in `httpd.conf`
  (also on by default).

## 5. Serving from the default `www` folder vs. a vhost

Dropping the project into `wamp64\www\aikaraoke\` and visiting
`http://localhost/aikaraoke/` works out of the box — the root
`.htaccess` forwards every request into `public/` (see
`public/index.php`, which auto-detects this and strips the `/aikaraoke`
prefix from routing regardless of which way it's served). For anything
beyond local development, prefer a WAMP vhost (via
**wampmanager → Apache → httpd-vhosts.conf**) with `DocumentRoot`
pointing straight at `public/`, and delete the root `.htaccess`.

## 6. Installing FFmpeg on Windows

FFmpeg isn't a pip package — download a Windows build (the "essentials"
build from [gyan.dev](https://www.gyan.dev/ffmpeg/builds/) is what this
project was built and tested against) and extract it somewhere stable,
e.g. `python\tools\ffmpeg\`. Point **Settings → FFmpeg Path / FFprobe
Path** at `...\ffmpeg\bin\ffmpeg.exe` / `ffprobe.exe`. Confirm the build
includes `--enable-libass` and `--enable-libx264` (`ffmpeg -version` lists
its build config) — both are required for karaoke subtitle burn-in and
H.264 encoding.

## 7. Windows-specific process spawning

PHP spawns the Python worker as a detached background process using
`cmd /c start "" /B ...` via `proc_open()` (see
`App\Services\JobService::spawnWorker()`) so the HTTP request returns
immediately instead of blocking for the whole job. This is Windows-only
plumbing — the Linux path in the same method uses `nohup ... &` instead
and is what runs in production (see
[DEPLOYMENT_UBUNTU.md](DEPLOYMENT_UBUNTU.md)).

## 8. Memory headroom for WhisperX

WhisperX (used for lyrics transcription/alignment) loads a real speech
model into memory. On a modest/shared Windows VM, the `medium` model can
fail with `mkl_malloc: failed to allocate memory` if there isn't enough
free RAM alongside Apache/MySQL. The default WhisperX model shipped in
this project's seed data is **`base`** specifically for this reason —
bump it to `small`/`medium`/`large` in Settings once you've confirmed your
machine has the headroom (a few GB free is a reasonable minimum for
`medium`).
