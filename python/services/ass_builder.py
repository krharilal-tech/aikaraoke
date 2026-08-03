"""
Builds an .ass subtitle file that renders word-by-word karaoke highlighting:
current word yellow, upcoming words white, already-sung words light gray,
each with a black outline + soft shadow (via the ASS style, burned in by
ffmpeg's libass-based `subtitles` filter).

Words are grouped into display lines using a pause/length heuristic — the
word-level JSON has no line breaks of its own (WhisperX gives flat
word timing regardless of whether the text came from a lyrics provider or
ASR), so a natural-feeling line break is inferred from singing pauses.
"""

from __future__ import annotations

from dataclasses import dataclass
from typing import Any

COLOR_PAST = "&HD3D3D3&"    # light gray, BGR
COLOR_CURRENT = "&H00FFFF&"  # yellow, BGR
COLOR_FUTURE = "&HFFFFFF&"   # white, BGR

MAX_WORDS_PER_LINE = 8
MAX_LINE_DURATION = 7.0
PAUSE_THRESHOLD = 0.7
LEAD_IN_SECONDS = 1.0


@dataclass
class Word:
    text: str
    start: float
    end: float


def group_words_into_lines(words: list[dict[str, Any]]) -> list[list[Word]]:
    lines: list[list[Word]] = []
    current: list[Word] = []

    for raw in words:
        word = Word(text=str(raw["word"]), start=float(raw["start"]), end=float(raw["end"]))

        if current:
            gap = word.start - current[-1].end
            span_if_added = word.end - current[0].start

            if gap > PAUSE_THRESHOLD or len(current) >= MAX_WORDS_PER_LINE or span_if_added > MAX_LINE_DURATION:
                lines.append(current)
                current = []

        current.append(word)

    if current:
        lines.append(current)

    return lines


def _format_timestamp(seconds: float) -> str:
    seconds = max(0.0, seconds)
    hours = int(seconds // 3600)
    minutes = int((seconds % 3600) // 60)
    secs = seconds % 60

    return f"{hours:01d}:{minutes:02d}:{secs:05.2f}"


def _line_text(line: list[Word], current_index: int | None) -> str:
    parts = []

    for index, word in enumerate(line):
        if current_index is None or index < current_index:
            color = COLOR_PAST if current_index is not None else COLOR_FUTURE
        elif index == current_index:
            color = COLOR_CURRENT
        else:
            color = COLOR_FUTURE

        override = f"{{\\c{color}}}"

        if index == current_index:
            # Quick pop/scale pulse on the currently-sung word for a "smooth
            # fade and scale animation" feel, per spec.
            override = f"{{\\c{color}\\t(0,120,\\fscx112\\fscy112)\\t(120,260,\\fscx100\\fscy100)}}"

        parts.append(override + word.text)

    return r"\N".join(_wrap_line(parts))


def _wrap_line(parts: list[str]) -> list[str]:
    """Joins words with spaces but keeps the override-tag/text pairing
    intact; kept as its own function so line-wrapping logic (currently a
    no-op — lines are already length-capped) has one place to grow."""

    return [" ".join(parts)]


def build_ass(words: list[dict[str, Any]], resolution: tuple[int, int] = (1920, 1080)) -> str:
    width, height = resolution
    font_size = max(36, height // 16)
    margin_v = height // 10

    header = f"""[Script Info]
ScriptType: v4.00+
PlayResX: {width}
PlayResY: {height}
WrapStyle: 0
ScaledBorderAndShadow: yes

[V4+ Styles]
Format: Name, Fontname, Fontsize, PrimaryColour, SecondaryColour, OutlineColour, BackColour, Bold, Italic, Underline, StrikeOut, ScaleX, ScaleY, Spacing, Angle, BorderStyle, Outline, Shadow, Alignment, MarginL, MarginR, MarginV, Encoding
Style: Default,Arial,{font_size},&HFFFFFF&,&H000000FF&,&H000000&,&H80000000&,1,0,0,0,100,100,0,0,1,3,2,2,60,60,{margin_v},1

[Events]
Format: Layer, Start, End, Style, Name, MarginL, MarginR, MarginV, Effect, Text
"""

    lines = group_words_into_lines(words)
    events = []

    for line in lines:
        line_start = line[0].start
        line_end = line[-1].end

        for index, word in enumerate(line):
            event_start = word.start
            event_end = line[index + 1].start if index + 1 < len(line) else word.end

            text = _line_text(line, current_index=index)
            fade = ""

            if index == 0:
                lead_in = min(LEAD_IN_SECONDS, event_start)
                event_start -= lead_in
                fade = r"\fad(300,0)"

            if index == len(line) - 1:
                fade = r"\fad(0,300)" if not fade else r"\fad(300,300)"

            prefix = f"{{{fade}}}" if fade else ""

            events.append(
                f"Dialogue: 0,{_format_timestamp(event_start)},{_format_timestamp(event_end)},"
                f"Default,,0,0,0,,{prefix}{text}"
            )

    return header + "\n".join(events) + "\n"


def write_ass_file(words: list[dict[str, Any]], output_path: str, resolution: tuple[int, int] = (1920, 1080)) -> None:
    content = build_ass(words, resolution)

    with open(output_path, "w", encoding="utf-8") as handle:
        handle.write(content)
