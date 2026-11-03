"""
Thin wrapper around WhisperX: ASR transcription (used only when no lyrics
provider had a match) and forced word-level alignment (used always, since
none of the lyrics providers return word-level timing).
"""

from __future__ import annotations

import gc
import os
from typing import Any

import numpy as np
import whisperx
from whisperx.alignment import DEFAULT_ALIGN_MODELS_HF, DEFAULT_ALIGN_MODELS_TORCH

# whisperx.load_audio() always resamples to this.
ALIGN_SAMPLE_RATE = 16000

# A line's aligned words must cover this share of its sung time...
_MIN_LINE_COVERAGE = 0.5

# ...unless it's too short for that to matter.
_MIN_RESPREAD_SECONDS = 1.0

# Less singing than this in a line's window is noise, not the line.
_MIN_SUNG_LINE_SECONDS = 0.3


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
    which embeds OmegaConf objects alongside the tensor weights:
    "WeightsUnpickler error: Unsupported global: GLOBAL
    omegaconf.listconfig.ListConfig was not an allowed global by default."

    Monkey-patching torch.load's default itself (the first fix PyTorch's
    error message suggests) turned out not to work here — confirmed live
    against a real job — because whatever actually loads this checkpoint
    (pytorch_lightning's own checkpoint loader, going by "Lightning
    automatically upgraded your loaded checkpoint..." in the logs) had
    already bound its own reference to the original torch.load before this
    module's patch ever ran, so reassigning torch.load afterward didn't
    reach it. torch.serialization.add_safe_globals(...) doesn't have that
    problem — it adds to a shared registry torch's unpickler consults at
    unpickling time, not a specific call site, so it works regardless of
    which code path or import order got there first.

    Allowlisting DictConfig/ContainerMetadata/Metadata alongside the
    ListConfig actually named in the error is deliberately preemptive:
    OmegaConf structures nearly always nest more than one of these
    together, and each is exactly the kind of low-level container/metadata
    type that's safe to trust from a well-known source. Every checkpoint
    this app loads comes from one — WhisperX/pyannote's own published
    models, the Malayalam-specific HuggingFace checkpoint — never an
    arbitrary user upload."""

    import torch
    from omegaconf.base import ContainerMetadata, Metadata
    from omegaconf.dictconfig import DictConfig
    from omegaconf.listconfig import ListConfig

    torch.serialization.add_safe_globals([ListConfig, DictConfig, ContainerMetadata, Metadata])


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


def _loudness(audio: Any) -> Any:
    """Vocal loudness every 1/20th of a second."""

    hop = ALIGN_SAMPLE_RATE // 20
    frames = audio[: audio.size - audio.size % hop].reshape(-1, hop)

    return np.sqrt((frames ** 2).mean(axis=1))


def _singing_threshold(loudness: Any) -> float:
    return max(float(np.percentile(loudness, 95)) * 0.1, 1e-4)


def _sung_span(loudness: Any, threshold: float, start: float, end: float) -> tuple[float, float] | None:
    """The first and last moments in [start, end) with singing in them. The
    threshold is the whole song's, since a soft line is still singing."""

    first_frame = int(start * 20)
    sung = np.flatnonzero(loudness[first_frame:int(end * 20)] > threshold)

    if sung.size == 0:
        return None

    return (first_frame + sung[0]) / 20, (first_frame + sung[-1] + 1) / 20


def snap_lines_to_singing(audio_path: str, segments: list[dict[str, Any]]) -> tuple[list[dict[str, Any]], int]:
    """Published line times are hand-made, sometimes for a different cut of the
    song, so a line can be tagged where this recording has no singing. Those
    lines join the next line that does have singing, and each line's window
    runs for as long as the singing continues before the next line. Returns
    (segments, number of lines moved)."""

    loudness = _loudness(whisperx.load_audio(audio_path))
    threshold = _singing_threshold(loudness)
    snapped: list[dict[str, Any]] = []
    waiting: list[str] = []
    moved = 0

    for index, segment in enumerate(segments):
        start = float(segment["start"])
        span = _sung_span(loudness, threshold, start, float(segment["end"]))

        if span is None or span[1] - span[0] < _MIN_SUNG_LINE_SECONDS:
            waiting.append(str(segment["text"]))
            moved += 1
            continue

        next_start = float(segments[index + 1]["start"]) if index + 1 < len(segments) else float(segment["end"])
        sung = _sung_span(loudness, threshold, start, max(next_start, span[1])) or span
        snapped.append({"text": " ".join([*waiting, str(segment["text"])]), "start": sung[0], "end": sung[1]})
        waiting = []

    if waiting and snapped:
        snapped[-1]["text"] = " ".join([snapped[-1]["text"], *waiting])

    return (snapped, moved) if snapped else (segments, 0)


def spread_bunched_lines(
    audio_path: str,
    segments: list[dict[str, Any]],
    words: list[dict[str, Any]],
) -> tuple[list[dict[str, Any]], int]:
    """The alignment models are trained on speech, so on held notes they can
    squeeze a whole line into its first moments, even when the line itself is
    in the right place (real example: a 4.4s line aligned into 0.6s). Respreads
    those lines' words across the sung part of the line, by character count.
    Returns (words, number of lines respread)."""

    line_lengths = [len(str(segment.get("text", "")).split()) for segment in segments]

    # Alignment can skip words it can't place, and then there's no telling
    # which line each remaining word belongs to.
    if sum(line_lengths) != len(words):
        return words, 0

    loudness = _loudness(whisperx.load_audio(audio_path))
    threshold = _singing_threshold(loudness)
    result: list[dict[str, Any]] = []
    respread = 0
    position = 0

    for segment, length in zip(segments, line_lengths):
        line_words = words[position:position + length]
        position += length
        span = _sung_span(loudness, threshold, float(segment["start"]), float(segment["end"])) if line_words else None

        if span is None or span[1] - span[0] < _MIN_RESPREAD_SECONDS:
            result.extend(line_words)
            continue

        aligned = line_words[-1]["end"] - line_words[0]["start"]

        if aligned >= (span[1] - span[0]) * _MIN_LINE_COVERAGE:
            result.extend(line_words)
            continue

        respread += 1
        total_chars = sum(len(word["word"]) for word in line_words) or 1
        cursor = span[0]

        for word in line_words:
            duration = (span[1] - span[0]) * len(word["word"]) / total_chars
            result.append({"word": word["word"], "start": round(cursor, 3), "end": round(cursor + duration, 3)})
            cursor += duration

    # Line windows start slightly early, so a line can begin before the last
    # one's final word ends. Only one word should be highlighted at a time.
    for word, next_word in zip(result, result[1:]):
        next_word["start"] = max(next_word["start"], word["start"])
        next_word["end"] = max(next_word["end"], next_word["start"])
        word["end"] = min(word["end"], next_word["start"])

    return result, respread


def has_alignment_model(language: str) -> bool:
    """Whether align_words() can find real word boundaries for this language
    rather than falling back to _distribute_words_evenly()."""

    return language in DEFAULT_ALIGN_MODELS_TORCH or language in DEFAULT_ALIGN_MODELS_HF


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
