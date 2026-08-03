# Ubuntu Deployment Guide

Production deployment on Ubuntu 22.04/24.04 with Apache.

## 1. System packages

```bash
sudo apt update
sudo apt install -y apache2 mysql-server php8.1 php8.1-mysql php8.1-mbstring \
  php8.1-xml php8.1-cli composer python3 python3-venv python3-pip ffmpeg \
  fonts-lohit-mlym fonts-lohit-taml fonts-lohit-deva git
```

Confirm FFmpeg has the codecs this app needs:

```bash
ffmpeg -version | grep -o -- '--enable-libass\|--enable-libx264'
```

If your distro's FFmpeg build lacks `libass` or `libx264`, install a
static build instead (e.g. from johnvansickle.com) and point Settings at
its path.

The `fonts-lohit-*` packages matter as much as libass itself: the karaoke
subtitle style requests "Arial" (`python/services/ass_builder.py`), which
has no Malayalam/Tamil/Hindi glyphs. Desktop OSes silently substitute a
font that does; a bare server has no such fallback, so without fonts that
cover those scripts installed, libass renders empty boxes (`.notdef`
tofu) for every non-Latin lyric instead of erroring — easy to miss until
a real non-English song is rendered. Lohit covers exactly the non-Latin
languages this app transcribes (Tamil/Malayalam/Hindi) without pulling in
the much larger `fonts-noto` metapackage's CJK/emoji/dozens-of-scripts
bulk this app never uses.

## 2. Deploy the code

```bash
sudo mkdir -p /var/www/aikaraoke
sudo chown -R $USER:www-data /var/www/aikaraoke
git clone <this repo> /var/www/aikaraoke
cd /var/www/aikaraoke
composer install --no-dev --optimize-autoloader
```

## 3. Apache vhost (DocumentRoot = public/)

Unlike the WAMP quick-start (which forwards a shared `www/` folder via the
root `.htaccess`), production should point `DocumentRoot` straight at
`public/` and skip that forwarding layer entirely:

```apache
<VirtualHost *:80>
    ServerName karaoke.example.com
    DocumentRoot /var/www/aikaraoke/public

    <Directory /var/www/aikaraoke/public>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/aikaraoke-error.log
    CustomLog ${APACHE_LOG_DIR}/aikaraoke-access.log combined
</VirtualHost>
```

```bash
sudo a2enmod rewrite
sudo a2ensite aikaraoke
sudo systemctl reload apache2
```

Put this behind TLS (`certbot --apache`) before exposing it publicly —
the app handles login credentials and an OpenAI API key.

## 4. File permissions

The web server user (`www-data`) needs to write to `storage/` and `.env`:

```bash
sudo chown -R www-data:www-data /var/www/aikaraoke/storage
sudo chmod -R 775 /var/www/aikaraoke/storage
touch /var/www/aikaraoke/.env
sudo chown www-data:www-data /var/www/aikaraoke/.env
```

## 5. Database

```bash
sudo mysql -e "CREATE DATABASE aikaraoke CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER 'aikaraoke'@'localhost' IDENTIFIED BY 'change-me';"
sudo mysql -e "GRANT ALL PRIVILEGES ON aikaraoke.* TO 'aikaraoke'@'localhost';"
```

Then run the install wizard at `https://karaoke.example.com/install/` as
usual (see [INSTALLATION.md](INSTALLATION.md)) — it will run the schema
migration and create your admin account. **Remember the wizard permanently
locks itself once an admin exists**, so there's no separate "disable the
installer" step to remember.

## 6. Python worker environment

```bash
cd /var/www/aikaraoke/python
./setup.sh
```

Set **Python Path** in Settings to
`/var/www/aikaraoke/python/venv/bin/python` and **FFmpeg Path**/**FFprobe
Path** to `ffmpeg`/`ffprobe` (or their full paths from `which ffmpeg`).

## 7. Process spawning on Linux

`App\Services\JobService::spawnWorker()` detects it's not on Windows and
uses `nohup <python> worker.py --job-id N --root <path> > log 2>&1 &`
via `proc_open()`, which detaches cleanly under Linux without the
`cmd /c start /B` trick WAMP needs. No additional process manager
(systemd unit, supervisor, etc.) is required — each job is a short-lived
detached process, not a long-running daemon.

## 8. Reverse proxy / rate limiting caveat

`App\Core\RateLimiter` keys its buckets on `$_SERVER['REMOTE_ADDR']`. If
you put this behind a reverse proxy or load balancer, every request will
appear to come from the proxy's IP unless you configure Apache to trust
`X-Forwarded-For` (`mod_remoteip`) and update `Request::ip()`
accordingly — otherwise all users share one rate-limit bucket.

## 9. Backups

Back up two things: the `aikaraoke` MySQL database, and `storage/jobs/`
(source audio, stems, generated backgrounds, and rendered videos all live
there — they are not stored in the database, only referenced by path).
`.env` should be backed up separately and kept out of any public backup
bucket (it holds your OpenAI key and DB credentials).
