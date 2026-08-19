#!/bin/sh
# Starts the PO token provider's Node HTTP server in the background, then
# hands off to the actual worker. yt-dlp's plugin auto-detects it on the
# default 127.0.0.1:4416 with zero extra config (see Dockerfile) — nothing
# in python/ needs to know this server exists.
set -e

node /opt/bgutil-pot/server/build/main.js &

# Brief pause so the server is already listening before the first job's
# yt-dlp call needs it. The plugin has its own retry/timeout on top of
# this, so this is just to avoid relying on that alone right after a cold
# start, not a strict readiness guarantee.
sleep 2

exec python -u handler.py
