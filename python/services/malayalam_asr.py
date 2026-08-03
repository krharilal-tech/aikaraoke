"""
Malayalam-specific ASR fallback.

Generic multilingual Whisper — tested at base/small/medium — reliably fails
to render actual Malayalam script for sung audio: it decodes into Tamil
script, or a garbled Latin/Devanagari mix, regardless of model size (see
docs/TROUBLESHOOTING.md). Whisper's own paper documents Malayalam as one of
its worst-covered languages by training-hours, which is the root cause.

thennal/whisper-medium-ml is a community fine-tune of Whisper Medium
trained specifically on Malayalam speech corpora (Common Voice 11.0,
FLEURS, IMaSC, indic_tts_ml — ~11.5% WER on the Common Voice eval set) and
reliably produces correct Malayalam script in testing against this app's
real audio. Used in place of the generic model whenever the transcription
language is Malayalam.

Deliberately bypasses transformers' `pipeline("automatic-speech-
recognition", ...)` helper: its long-form chunking path
(`chunk_length_s=...`) imports `torchcodec` internally regardless of input
type, and torchcodec fails to load in this environment (same underlying
FFmpeg/DLL issue noted elsewhere in this codebase). Chunking 30s windows by
hand and calling the model directly sidesteps that entirely — WhisperX's
own `load_audio()` (an ffmpeg subprocess call, no torchcodec involved) is
reused to decode the source file.
"""

from __future__ import annotations

import gc
from typing import Any

MODEL_ID = "thennal/whisper-medium-ml"
SAMPLE_RATE = 16000
CHUNK_SECONDS = 30
# Whisper's decoder position limit is 448; forced_decoder_ids for
# language+task occupies a few of those, so max_new_tokens must leave room.
MAX_NEW_TOKENS = 440


def transcribe_malayalam(audio_path: str) -> dict[str, Any]:
    import torch
    import whisperx
    from transformers import WhisperForConditionalGeneration, WhisperProcessor

    from services.whisperx_engine import select_device

    # This model used to never move itself (or its inputs) off the default
    # CPU placement transformers gives a freshly-loaded model — harmless
    # locally (no GPU either way), but it meant this specific model, the
    # slowest step in the whole pipeline for exactly the songs this app's
    # target users care about most (30-50 minutes on CPU for a single
    # song), never actually used RunPod's GPU either.
    device = select_device()

    audio = whisperx.load_audio(audio_path)

    processor = WhisperProcessor.from_pretrained(MODEL_ID)
    model = WhisperForConditionalGeneration.from_pretrained(MODEL_ID).to(device)
    model.eval()
    forced_decoder_ids = processor.get_decoder_prompt_ids(language="ml", task="transcribe")

    try:
        chunk_samples = CHUNK_SECONDS * SAMPLE_RATE
        segments: list[dict[str, Any]] = []

        for start_sample in range(0, len(audio), chunk_samples):
            chunk = audio[start_sample:start_sample + chunk_samples]

            if len(chunk) < SAMPLE_RATE * 0.5:
                # Trailing sliver shorter than half a second — not enough
                # signal to transcribe meaningfully, and Whisper chokes on
                # near-empty input.
                continue

            inputs = processor(chunk, sampling_rate=SAMPLE_RATE, return_tensors="pt").to(device)

            with torch.no_grad():
                predicted_ids = model.generate(
                    inputs.input_features,
                    forced_decoder_ids=forced_decoder_ids,
                    max_new_tokens=MAX_NEW_TOKENS,
                )

            text = processor.batch_decode(predicted_ids, skip_special_tokens=True)[0].strip()

            if not text:
                continue

            start_time = start_sample / SAMPLE_RATE
            end_time = min(start_sample + chunk_samples, len(audio)) / SAMPLE_RATE
            segments.append({"text": text, "start": round(start_time, 3), "end": round(end_time, 3)})

        return {"segments": segments, "language": "ml"}
    finally:
        del model, processor
        gc.collect()
