"""
Thin wrapper around WhisperX: ASR transcription (used only when no lyrics
provider had a match) and forced word-level alignment (used always, since
none of the lyrics providers return word-level timing).
"""

from __future__ import annotations

import gc
import os
from typing import Any

import whisperx


def _disable_huggingface_symlinks() -> None:
    """faster-whisper/WhisperX download model weights via huggingface_hub,
    which caches them using symlinks (blob + a symlinked "pointer" file) to
    avoid duplicating data on disk. On Windows, creating a symlink needs
    SeCreateSymbolicLinkPrivilege, which standard (non-admin, non-Developer-
    Mode) accounts don't have — that alone huggingface_hub handles fine, via
    a startup probe that detects "no symlink support" and falls back to
    copying files instead.

    The problem actually hit in production: that probe and the *real*
    download can disagree — the probe reports symlinks as supported, but
    the real symlink call still raises "[WinError 1314] A required
    privilege is not held by the client", and unlike the probe, that call
    site has no fallback, so it crashes the whole job. Monkey-patching
    `are_symlinks_supported` to unconditionally return False makes every
    caller take the already-implemented, already-safe "copy instead of
    symlink" path every time, regardless of what the probe would have said
    — trading a little extra disk space for cached models against a crash
    that otherwise reappears unpredictably on any non-elevated Windows
    account. (The alternative fix is enabling Windows Developer Mode
    machine-wide, which grants the privilege outright — but that's an OS
    setting we can't rely on end users having set on every machine this
    ships to, so we fix it in code instead.)"""

    if os.name != "nt":
        return

    import huggingface_hub.file_download as hf_file_download

    hf_file_download.are_symlinks_supported = lambda cache_dir=None: False


def _allow_legacy_torch_checkpoint_loading() -> None:
    """PyTorch 2.6 flipped torch.load()'s weights_only default from False to
    True — a real security improvement (blocks arbitrary code execution
    from untrusted pickled checkpoints), but it broke loading pyannote.audio's
    pretrained VAD checkpoint (used internally by whisperx.load_model() for
    the vad_filter=True path — see _detect_restricted_language() below),
    which embeds an omegaconf.listconfig.ListConfig object alongside the
    tensor weights: "WeightsUnpickler error: Unsupported global: GLOBAL
    omegaconf.listconfig.ListConfig was not an allowed global by default."

    PyTorch's own error message offers two fixes: allowlist the specific
    class via torch.serialization.add_safe_globals(...), or restore the old
    default entirely. Allowlisting one class at a time risks a
    whack-a-mole cycle — the next model with a different embedded config
    type would just hit the same wall again. Since every checkpoint this
    app loads comes from a well-known, trusted source (WhisperX/pyannote's
    own published models, Meta's Demucs releases, the Malayalam-specific
    HuggingFace checkpoint — never an arbitrary user upload), restoring the
    permissive pre-2.6 default for this process is the pragmatic choice:
    only override the default when a caller doesn't explicitly set
    weights_only itself, so nothing here silently overrides an intentional
    True elsewhere."""

    import torch

    original_load = torch.load

    def _patched_load(*args: Any, **kwargs: Any) -> Any:
        kwargs.setdefault("weights_only", False)
        return original_load(*args, **kwargs)

    torch.load = _patched_load


_disable_huggingface_symlinks()
_allow_legacy_torch_checkpoint_loading()


def select_device() -> str:
    """Shared by every stage that loads a torch model (this module,
    stages/separate_vocals.py, services/malayalam_asr.py) — these all used
    to hardcode "cpu" unconditionally, which was harmless on the WAMP dev
    box (no GPU either way) but meant none of them actually used RunPod's
    GPU once processing moved there, defeating half the point of that
    migration. "cuda" when one's actually available, "cpu" otherwise
    (still correct locally)."""

    import torch

    return "cuda" if torch.cuda.is_available() else "cpu"


# Keep in sync with the <select> in app/Views/settings/index.php — these are
# the only languages "Transcription Language: Auto-detect" is allowed to
# land on. Restricting the choice to languages this app actually supports
# (rather than letting Whisper pick its single best guess out of the ~99 it
# knows) is most of what makes auto-detect reliable in practice.
CANDIDATE_LANGUAGES = ["ta", "ml", "hi", "en"]


def _detect_restricted_language(
    pipeline: Any,
    audio: Any,
    candidates: list[str],
) -> tuple[str, float]:
    """Whisper's own top-1 auto-detect only looks at the first ~30s of
    audio and returns whichever of the ~99 languages it knows scored
    highest overall. Both halves of that are a problem for film songs: (1)
    a long instrumental intro means the sampled window may contain no
    singing at all to identify, and (2) an unrestricted 99-way choice can
    land on a language nobody asked for even when it does hear singing —
    this is exactly how a real Tamil song ended up transcribed as English.

    This fixes both: `vad_filter=True` + `language_detection_segments=3`
    skips non-speech stretches and samples several 30s windows instead of
    just the first, and the final answer is whichever of `candidates`
    scored highest — never a language outside that set."""

    _, _, all_language_probs = pipeline.model.detect_language(
        audio=audio,
        vad_filter=True,
        language_detection_segments=3,
    )
    scores = dict(all_language_probs)
    best = max(candidates, key=lambda code: scores.get(code, 0.0))

    return best, scores.get(best, 0.0)


def transcribe(
    audio_path: str,
    model_name: str,
    device: str = "cpu",
    language: str | None = None,
    candidates: list[str] | None = None,
) -> dict[str, Any]:
    """language=None resolves the language via `_detect_restricted_language()`
    (scoped to `candidates`, defaulting to CANDIDATE_LANGUAGES) instead of
    leaving WhisperX's own unrestricted global auto-detect in charge. Pass
    an explicit code (e.g. "ta"/"ml"/"hi"/"en") to force it outright and
    skip detection entirely."""

    if language == "ml":
        # Generic multilingual Whisper reliably fails to render actual
        # Malayalam script (tested base/small/medium — see
        # docs/TROUBLESHOOTING.md) regardless of size. Skip loading it
        # altogether and go straight to the Malayalam-specific checkpoint.
        from services.malayalam_asr import transcribe_malayalam

        return transcribe_malayalam(audio_path)

    compute_type = "int8" if device == "cpu" else "float16"
    pipeline = whisperx.load_model(model_name, device, compute_type=compute_type)

    try:
        audio = whisperx.load_audio(audio_path)

        if language is None:
            language, _score = _detect_restricted_language(pipeline, audio, candidates or CANDIDATE_LANGUAGES)

            if language == "ml":
                from services.malayalam_asr import transcribe_malayalam

                return transcribe_malayalam(audio_path)

        return pipeline.transcribe(audio, batch_size=8, language=language)
    finally:
        del pipeline
        gc.collect()


def build_segments_from_text(text: str, duration_sec: float) -> list[dict[str, Any]]:
    """Turn plain lyrics text (no timing at all) into rough segments spanning
    the audio, proportional to each line's share of the total character
    count. This is only a starting hint — the CTC forced-alignment model in
    align_words() finds the real word boundaries; it just needs the text
    roughly located in time first."""

    lines = [line.strip() for line in text.splitlines() if line.strip()]

    if not lines:
        return []

    total_chars = sum(len(line) for line in lines) or 1
    segments = []
    cursor = 0.0

    for line in lines:
        share = len(line) / total_chars
        span = duration_sec * share
        segments.append({"text": line, "start": cursor, "end": min(cursor + span, duration_sec)})
        cursor += span

    return segments


def _distribute_words_evenly(segments: list[dict[str, Any]]) -> list[dict[str, Any]]:
    """Fallback for languages with no forced-alignment model available (see
    align_words() below) — spreads each segment's words evenly across that
    segment's own [start, end) window instead of finding real per-word
    boundaries. Less precise than CTC alignment (a word's highlighted
    duration won't track its actual sung timing as tightly), but every
    segment already has a reasonable start/end — from WhisperX's own ASR
    segmentation, or from build_segments_from_text()'s per-line estimate —
    so this still gives usable word-level output instead of failing the
    whole job."""

    words: list[dict[str, Any]] = []

    for segment in segments:
        segment_words = str(segment.get("text", "")).split()

        if not segment_words:
            continue

        start = float(segment["start"])
        end = float(segment["end"])
        per_word = max(end - start, 0.01) / len(segment_words)

        for index, word in enumerate(segment_words):
            words.append({
                "word": word,
                "start": round(start + index * per_word, 3),
                "end": round(start + (index + 1) * per_word, 3),
            })

    return words


def align_words(
    audio_path: str,
    segments: list[dict[str, Any]],
    language: str,
    device: str = "cpu",
) -> tuple[list[dict[str, Any]], bool]:
    """Returns (words, used_forced_alignment) — the caller logs differently
    depending on which path was taken."""

    if not segments:
        return [], True

    try:
        align_model, metadata = whisperx.load_align_model(language_code=language, device=device)
    except ValueError:
        # No default forced-alignment model for this language — e.g. WhisperX
        # ships one for Hindi and Malayalam but not Tamil (see
        # venv/Lib/site-packages/whisperx/alignment.py's
        # DEFAULT_ALIGN_MODELS_*  dicts). Falling back keeps the job working
        # end-to-end instead of crashing "Synchronizing Lyrics" outright.
        return _distribute_words_evenly(segments), False

    audio = whisperx.load_audio(audio_path)

    try:
        result = whisperx.align(segments, align_model, metadata, audio, device, return_char_alignments=False)
    finally:
        del align_model
        gc.collect()

    words: list[dict[str, Any]] = []

    for segment in result.get("segments", []):
        for word in segment.get("words", []):
            if "start" not in word or "end" not in word:
                # WhisperX can't always place a word (e.g. it falls in a
                # noisy/instrumental gap) — skip rather than fabricate timing.
                continue

            words.append({
                "word": word["word"].strip(),
                "start": round(float(word["start"]), 3),
                "end": round(float(word["end"]), 3),
            })

    return words, True
