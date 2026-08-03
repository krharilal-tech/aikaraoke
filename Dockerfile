# RunPod Serverless GPU worker image — runs python/handler.py.
# Only the python/ directory matters here; the PHP app (app/, public/,
# vendor/) never runs on this side, it stays on Hostinger. Deployed via
# RunPod's GitHub integration (docs.runpod.io/serverless/workers/github-integration),
# which builds this on RunPod's own infrastructure — no local Docker needed.

FROM runpod/base:0.6.3-cuda11.8.0

WORKDIR /app

# Same libass check as docs/DEPLOYMENT_UBUNTU.md flags for the plain VPS
# path — the karaoke subtitle burn-in needs it, and not every distro
# ffmpeg package ships it by default.
RUN apt-get update \
    && apt-get install -y --no-install-recommends ffmpeg \
    && rm -rf /var/lib/apt/lists/* \
    && ffmpeg -version | grep -q -- '--enable-libass' \
    || (echo "FFmpeg build is missing libass support" && exit 1)

COPY python/requirements.txt /app/requirements.txt

# CUDA-enabled torch/torchaudio, matching this base image's CUDA 11.8 —
# installed *before* requirements.txt on purpose, exactly like
# python/setup.sh does for the CPU build locally: requirements.txt's own
# "torch>=2.2.0" line is then already satisfied and left alone, rather
# than pip resolving some other (possibly CPU-only or mismatched-CUDA)
# wheel from PyPI on its own.
RUN pip install --no-cache-dir torch torchaudio --index-url https://download.pytorch.org/whl/cu118
RUN pip install --no-cache-dir -r requirements.txt

COPY python/ /app/

CMD ["python", "-u", "handler.py"]
