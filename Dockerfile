# RunPod Serverless GPU worker image — runs handler.py.
# handler.py itself lives at the repo root (RunPod's GitHub-import
# pre-flight check for runpod.serverless.start() only scans top-level
# files) and is a thin shim over the python/ package, which is otherwise
# unchanged and still used as-is by the local WAMP/Ubuntu subprocess path
# too. The PHP app (app/, public/, vendor/) never runs on this side, it
# stays on Hostinger. Deployed via RunPod's GitHub integration
# (docs.runpod.io/serverless/workers/github-integration), which builds
# this on RunPod's own infrastructure — no local Docker needed.

FROM runpod/base:0.6.3-cuda11.8.0

WORKDIR /app

# runpod/base doesn't put a plain `python`/`python3` on PATH by default —
# only pip (which has its own shebang straight to python3.11, which is why
# the `pip install` steps below work fine even without this) — but our
# CMD's bare `python` needs one, and without it the container starts and
# immediately dies with "exited with exit code 126" (found something
# named python, couldn't actually execute it) rather than failing the
# build, which is what made this so non-obvious to track down. Matches
# RunPod's own worker-template Dockerfile exactly.
RUN ln -sf $(which python3.11) /usr/local/bin/python && \
    ln -sf $(which python3.11) /usr/local/bin/python3

# Same libass check as docs/DEPLOYMENT_UBUNTU.md flags for the plain VPS
# path — the karaoke subtitle burn-in needs it, and not every distro
# ffmpeg package ships it by default.
#
# fonts-lohit-* matter as much as libass itself: the ASS style requests
# "Arial" (services/ass_builder.py), which has no Malayalam/Tamil/Hindi
# glyphs, and on Windows locally GDI silently substitutes a font that
# does. This bare Linux container has no such fallback, so without fonts
# that actually cover those scripts, libass renders .notdef boxes for
# every non-Latin lyric instead of erroring — a real job hit exactly this
# on a Malayalam song. The Lohit family covers exactly the non-Latin
# languages this app transcribes (CANDIDATE_LANGUAGES in
# services/whisperx_engine.py: Tamil, Malayalam, Hindi), each as a small,
# single-script package — deliberately not the much larger fonts-noto
# metapackage (CJK + emoji + dozens of scripts this app never renders),
# given the container's disk headroom is already tight.
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ffmpeg fonts-lohit-mlym fonts-lohit-taml fonts-lohit-deva git \
    && rm -rf /var/lib/apt/lists/* \
    && ffmpeg -version | grep -q -- '--enable-libass' \
    || (echo "FFmpeg build is missing libass support" && exit 1)

# --- PO token provider ---
# yt-dlp's "youtubepot-bgutilhttp" plugin (installed via
# requirements.txt) queries this local Node server for a valid
# proof-of-origin token instead of relying on cookies from a real Google
# account — see python/stages/download.py's docstring for why cookies
# alone were a dead end (Google's session tokens rotate independently of
# their listed expiry, so a static export goes stale within days
# regardless of how it was obtained). Built from source here rather than
# using the maintainers' published Docker image since it has to run
# alongside our own Python process in this one container, not as a
# separate compose service — RunPod's GitHub-integration build only
# builds this single Dockerfile. Version pinned to match the
# bgutil-ytdlp-pot-provider pip package pinned in requirements.txt; bump
# both together.
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/*

RUN git clone --depth 1 --single-branch --branch 1.3.1 \
        https://github.com/Brainicism/bgutil-ytdlp-pot-provider.git /opt/bgutil-pot \
    && cd /opt/bgutil-pot/server \
    && npm ci \
    && npx tsc \
    && npm prune --omit=dev

COPY python/requirements.txt /app/python/requirements.txt

# torch/torchaudio are installed as part of this single requirements.txt
# pass, pinned to exact versions there — NOT via a separate pre-install
# step from the CUDA-specific wheel index like this used to do. That
# separate step (installing torch==2.7.1+cu118 first, on the assumption
# that requirements.txt's own "torch>=2.2.0" would then already be
# satisfied and left alone) turned out to be wrong: pyannote-audio
# (a whisperx dependency) pulls in torchcodec, which pins a specific torch
# version tightly enough that pip's resolver silently reinstalled a
# *different* torch during the requirements.txt pass anyway, discarding
# the CUDA-11.8 wheel this step thought it had locked in — confirmed by
# reproducing the exact two-step install in an isolated venv. Standard
# Linux PyPI torch wheels bundle their own CUDA runtime (nvidia-cu12
# packages) regardless of the host image's own CUDA toolkit version, so
# letting requirements.txt resolve torch on its own — pinned to the exact
# version confirmed compatible with pyannote-audio's VAD checkpoint
# loading — gets GPU support without fighting pip's resolver.
RUN pip install --no-cache-dir -r python/requirements.txt

# Preserved as a real python/ subdirectory (not flattened into /app) —
# handler.py's sys.path.insert(0, .../"python") depends on this exact layout.
COPY python/ /app/python/
COPY handler.py /app/handler.py
COPY docker-entrypoint.sh /app/docker-entrypoint.sh
RUN chmod +x /app/docker-entrypoint.sh

CMD ["/app/docker-entrypoint.sh"]
