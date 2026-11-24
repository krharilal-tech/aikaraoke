"""
Whole-song lyrics alignment with Meta's MMS forced aligner (torchaudio's
MMS_FA pipeline).

The per-line WhisperX alignment only searches for a line's words inside a
time window someone has to guess first, and guessed windows drift (job 165:
the first line placed at 2.2s, sung at 24.4s). This aligns every word of the
lyrics against the whole vocal track in one pass instead, so the audio
itself decides where each line goes, and intros and instrumental breaks are
skipped because no word matches them.

MMS is one model for 1,100+ languages that reads text in English letters, so
Malayalam, Tamil and Hindi script are converted with uroman first. That also
gives Tamil real word timing, which WhisperX has no model for. A "*" token
between lines soaks up singing that isn't in the lyrics (humming, "aah").
"""

from __future__ import annotations

import gc
import re
from typing import Any

import torch
import torchaudio
import uroman
import whisperx

# uroman's codes for the languages this app supports; English needs none.
_UROMAN_LANGUAGES = {"ta": "tam", "ml": "mal", "hi": "hin"}

# The model is run on the song in chunks, each with some audio either side
# so words at a chunk's edge are still recognised.
_CHUNK_SECONDS = 15
_CONTEXT_SECONDS = 1

_romanizer: uroman.Uroman | None = None


def _romanize(word: str, language: str | None) -> str:
    """The word in the letters the MMS model knows: a-z and the apostrophe."""

    global _romanizer

    if _romanizer is None:
        _romanizer = uroman.Uroman()

    roman = _romanizer.romanize_string(word, lcode=_UROMAN_LANGUAGES.get(language or ""))

    return re.sub(r"[^a-z']", "", roman.lower())


def _emission(model: Any, audio: torch.Tensor, sample_rate: int, device: str) -> torch.Tensor:
    chunk = _CHUNK_SECONDS * sample_rate
    context = _CONTEXT_SECONDS * sample_rate
    total = audio.size(1)
    pieces = []

    with torch.inference_mode():
        for start in range(0, total, chunk):
            first, last = max(0, start - context), min(total, start + chunk + context)
            emission, _ = model(audio[:, first:last].to(device))
            emission = torch.log_softmax(emission, dim=-1)

            frames_per_sample = emission.size(1) / (last - first)
            keep_from = round((start - first) * frames_per_sample)
            keep_to = keep_from + round((min(total, start + chunk) - start) * frames_per_sample)
            pieces.append(emission[:, keep_from:keep_to])

    return torch.cat(pieces, dim=1)


def align_lines(audio_path: str, lines: list[str], language: str | None, device: str = "cpu") -> list[dict[str, Any]]:
    """Returns one {"word", "start", "end", "line"} per word of `lines`, in
    order. Words with nothing the model can read (a lone "—") take the end of
    the word before. Raises if the alignment can't be done, e.g. the lyrics
    are too long for the song."""

    words = [
        (line_index, word, _romanize(word, language))
        for line_index, line in enumerate(lines)
        for word in line.split()
    ]

    if not any(roman for _, _, roman in words):
        return []

    bundle = torchaudio.pipelines.MMS_FA
    dictionary = bundle.get_dict(star="*")
    model = bundle.get_model(with_star=True).to(device).eval()

    try:
        audio = torch.from_numpy(whisperx.load_audio(audio_path)).unsqueeze(0)
        emission = _emission(model, audio, bundle.sample_rate, device)
    finally:
        del model
        gc.collect()

        if device == "cuda":
            torch.cuda.empty_cache()

    # Each entry is (index into words, or None for a "*", token count).
    plan: list[tuple[int | None, int]] = []
    targets: list[int] = []
    previous_line = None

    for index, (line_index, _, roman) in enumerate(words):
        if line_index != previous_line:
            plan.append((None, 1))
            targets.append(dictionary["*"])
            previous_line = line_index

        if roman:
            plan.append((index, len(roman)))
            targets.extend(dictionary[letter] for letter in roman)

    plan.append((None, 1))
    targets.append(dictionary["*"])

    alignment, scores = torchaudio.functional.forced_align(
        emission, torch.tensor([targets], dtype=torch.int32, device=emission.device), blank=0
    )
    spans = torchaudio.functional.merge_tokens(alignment[0], scores[0].exp())
    seconds_per_frame = audio.size(1) / bundle.sample_rate / emission.size(1)

    timing: dict[int, tuple[float, float]] = {}
    position = 0

    for index, count in plan:
        word_spans = spans[position:position + count]
        position += count

        if index is not None:
            timing[index] = (word_spans[0].start * seconds_per_frame, word_spans[-1].end * seconds_per_frame)

    result = []
    previous_end = 0.0

    for index, (line_index, word, _) in enumerate(words):
        start, end = timing.get(index, (previous_end, previous_end))
        result.append({"word": word, "start": round(start, 3), "end": round(end, 3), "line": line_index})
        previous_end = end

    return result
