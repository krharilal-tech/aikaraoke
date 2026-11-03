"""
Lyrics text providers, tried in the order the spec requires:
LRCLIB -> Musixmatch -> Genius. None of these give word-level timing (LRCLIB's
"synced" lyrics are only line-level, Musixmatch/Genius give none at all) — the
"Synchronizing Lyrics" stage always runs WhisperX forced alignment afterward
to turn whatever plain text we get here into word-level timestamps.
"""

from __future__ import annotations

import html
import re
import time
from typing import Any, Callable

import requests

USER_AGENT = "AIKaraokeMaker/1.0 (+https://github.com/)"


# Upload tags left at the end of a title once any "(Official Video)"-style
# brackets and "|" segments are gone, e.g. "Poove Sempoove - Lyric Video".
_TRAILING_TITLE_TAGS = re.compile(
    r"(\s*[-–—]?\s*\b(official|lyrics?|lyrical|video song|full song|video|audio|hd|4k)\b)+\s*$",
    flags=re.IGNORECASE,
)

# How far an LRCLIB search result's length may be from the video's before
# it's treated as a different song or recording.
_MAX_DURATION_DIFF_SECONDS = 10

# LRCLIB regularly answers 503 "ServerOverloaded" for a few seconds at a time.
# Without retrying, a song it does have falls through to transcription.
_LRCLIB_RETRY_DELAYS_SECONDS = (2, 5, 10)

_LRC_TAG = re.compile(r"\[(\d{1,3}):(\d{2}(?:\.\d{1,3})?)\]")

# LRC times are hand-made, so a line can be tagged slightly after it starts.
_LRC_LINE_LEAD_SECONDS = 0.5

# The next timestamp can be far away when a line runs into an instrumental
# break, and a window that long lets a line's last word stretch across it.
_MAX_LRC_LINE_SECONDS = 15.0


def guess_artist_title(youtube_title: str, channel: str) -> tuple[str, str]:
    """YouTube gives us one title string and a channel name, not clean
    artist/track fields — this is a best-effort heuristic, not a guarantee."""

    cleaned = re.sub(
        r"[\(\[][^\)\]]*\b(official|video|audio|lyrics?|hd|4k|remaster\w*|visualizer|mv)\b[^\)\]]*[\)\]]",
        "",
        youtube_title,
        flags=re.IGNORECASE,
    ).strip(" -|")

    # Common convention for regional-language uploads, e.g.
    # "Kanchimmiyo | Cover Song Lyrics | KS Harishankar" — everything after
    # the first "|" is tagging noise that would otherwise sink a lyrics
    # provider search (no exact match on the full messy string).
    cleaned = cleaned.split("|", 1)[0].strip(" -–—") or cleaned
    cleaned = _TRAILING_TITLE_TAGS.sub("", cleaned).strip(" -–—") or cleaned

    for separator in (" - ", " – ", " — "):
        if separator in cleaned:
            artist, _, title = cleaned.partition(separator)
            return artist.strip(), title.strip()

    return channel.strip(), cleaned.strip()


def _lrclib_get(endpoint: str, params: dict[str, Any]) -> requests.Response | None:
    """Returns LRCLIB's answer (a 404 is a real "no match"), or None if it
    was still overloaded or unreachable after every retry."""

    for delay in (*_LRCLIB_RETRY_DELAYS_SECONDS, None):
        try:
            response = requests.get(
                f"https://lrclib.net/api/{endpoint}",
                params=params,
                headers={"User-Agent": USER_AGENT},
                timeout=10,
            )

            if response.status_code != 429 and response.status_code < 500:
                return response
        except requests.RequestException:
            pass

        if delay is not None:
            time.sleep(delay)

    return None


def fetch_lrclib(
    artist: str,
    title: str,
    duration_sec: int | None,
    log: Callable[[str], None] | None = None,
) -> str | None:
    """Returns the lyrics as published: LRC with line timestamps when LRCLIB
    has them, since alignment needs to know where each line roughly is."""

    unavailable = False
    plain_text = None

    def duration_diff(result: dict[str, Any]) -> float:
        return abs(float(result.get("duration") or 0) - duration_sec) if duration_sec else 0.0

    try:
        params = {"track_name": title, "artist_name": artist}
        if duration_sec:
            params["duration"] = duration_sec

        response = _lrclib_get("get", params)
        unavailable = response is None

        if response is not None and response.status_code == 200:
            data = response.json()
            if data.get("syncedLyrics"):
                return data["syncedLyrics"]

            plain_text = data.get("plainLyrics")

        # Fall back to search when there's no exact match, or no timed one.
        response = _lrclib_get("search", {"q": f"{artist} {title}"})
        unavailable = unavailable or response is None

        if response is not None and response.status_code == 200:
            results = response.json()
            if isinstance(results, list):
                # Free-text search returns its nearest guess even when the
                # song isn't there, so only trust a result of matching length.
                matches = [result for result in results if duration_diff(result) <= _MAX_DURATION_DIFF_SECONDS]
                synced = [result for result in matches if result.get("syncedLyrics")]

                if synced:
                    return min(synced, key=duration_diff)["syncedLyrics"]

                plain_text = plain_text or next((result["plainLyrics"] for result in matches if result.get("plainLyrics")), None)
    except ValueError:
        pass

    if plain_text:
        return plain_text

    if unavailable and log:
        log("LRCLIB unavailable (busy or unreachable after retries) — it may still have this song")

    return None


def parse_lrc_segments(text: str, duration_sec: int | None) -> list[dict[str, Any]] | None:
    """Turns LRC lines ("[00:12.34]word word") into segments for alignment, or
    None if the text carries no timestamps. WhisperX only searches for a line's
    words inside its segment, so these real times are what keep the highlight
    with the singing across intros and instrumental breaks. Blank LRC lines
    mark where singing stops."""

    entries = []

    for line in text.splitlines():
        tags = _LRC_TAG.findall(line)
        lyric = _LRC_TAG.sub("", line).strip()
        entries.extend((int(minutes) * 60 + float(seconds), lyric) for minutes, seconds in tags)

    entries.sort(key=lambda entry: entry[0])
    segments = []

    for index, (start, lyric) in enumerate(entries):
        if not lyric:
            continue

        end = start + _MAX_LRC_LINE_SECONDS

        if index + 1 < len(entries):
            end = min(end, entries[index + 1][0])

        if duration_sec:
            end = min(end, float(duration_sec))

        if end > start:
            segments.append({"text": lyric, "start": max(start - _LRC_LINE_LEAD_SECONDS, 0.0), "end": end})

    return segments or None


def _strip_lrc_timestamps(text: str) -> str:
    """Removes LRC's "[00:12.34]" tags, leaving the lyrics as plain text."""

    lines = [_LRC_TAG.sub("", line).strip() for line in text.splitlines()]
    return "\n".join(line for line in lines if line)


def fetch_musixmatch(artist: str, title: str, api_key: str) -> str | None:
    if not api_key:
        return None

    try:
        response = requests.get(
            "https://api.musixmatch.com/ws/1.1/matcher.lyrics.get",
            params={"q_track": title, "q_artist": artist, "apikey": api_key},
            headers={"User-Agent": USER_AGENT},
            timeout=10,
        )

        if response.status_code != 200:
            return None

        body = response.json().get("message", {}).get("body", {})
        lyrics_body = body.get("lyrics", {}).get("lyrics_body")

        if not lyrics_body:
            return None

        # Free-tier Musixmatch keys return a truncated snippet with this
        # marketing trailer appended — strip it so it doesn't end up burned
        # into the video.
        return lyrics_body.split("*******")[0].strip()
    except (requests.RequestException, ValueError):
        return None


def fetch_genius(artist: str, title: str, access_token: str) -> str | None:
    if not access_token:
        return None

    try:
        search = requests.get(
            "https://api.genius.com/search",
            params={"q": f"{artist} {title}"},
            headers={"Authorization": f"Bearer {access_token}", "User-Agent": USER_AGENT},
            timeout=10,
        )

        if search.status_code != 200:
            return None

        hits = search.json().get("response", {}).get("hits", [])

        if not hits:
            return None

        # Same as LRCLIB's search: the top hit can be a different song.
        if title.lower() not in hits[0]["result"].get("title", "").lower():
            return None

        song_url = hits[0]["result"]["url"]

        page = requests.get(song_url, headers={"User-Agent": USER_AGENT}, timeout=10)

        if page.status_code != 200:
            return None

        return _extract_genius_lyrics(page.text)
    except (requests.RequestException, ValueError, KeyError, IndexError):
        return None


def _extract_genius_lyrics(page_html: str) -> str | None:
    containers = re.findall(
        r'<div[^>]*data-lyrics-container="true"[^>]*>(.*?)</div>\s*(?:<div|$)',
        page_html,
        flags=re.DOTALL,
    )

    if not containers:
        return None

    text_parts = []

    for block in containers:
        block = re.sub(r"<br\s*/?>", "\n", block)
        block = re.sub(r"<[^>]+>", "", block)
        text_parts.append(html.unescape(block))

    text = "\n".join(text_parts).strip()

    return text or None


SCRIPT_RANGES = {
    "ta": (0x0B80, 0x0BFF),
    "ml": (0x0D00, 0x0D7F),
    "hi": (0x0900, 0x097F),
}


def detect_text_language(text: str) -> str:
    """Lyrics providers don't tag the language of the text they return, and
    unlike audio, written text can be identified deterministically by which
    Unicode script its characters fall in — no ambiguity like WhisperX's
    audio-based detection has. Counts characters per candidate script and
    returns whichever has the most hits; "en" if none of Tamil/Malayalam/
    Hindi's blocks appear at all."""

    counts = {code: 0 for code in SCRIPT_RANGES}

    for char in text:
        codepoint = ord(char)
        for code, (low, high) in SCRIPT_RANGES.items():
            if low <= codepoint <= high:
                counts[code] += 1
                break

    best = max(counts, key=lambda code: counts[code])
    return best if counts[best] > 0 else "en"


def get_lyrics_text(
    artist: str,
    title: str,
    duration_sec: int | None,
    musixmatch_api_key: str,
    genius_access_token: str,
    log: Callable[[str], None] | None = None,
) -> tuple[str | None, str | None, list[dict[str, Any]] | None]:
    """Returns (text, source, segments) from the first provider that has a
    match. segments is None unless the provider published line timing."""

    text = fetch_lrclib(artist, title, duration_sec, log)
    if text:
        return _strip_lrc_timestamps(text), "lrclib", parse_lrc_segments(text, duration_sec)

    text = fetch_musixmatch(artist, title, musixmatch_api_key)
    if text:
        return text, "musixmatch", None

    text = fetch_genius(artist, title, genius_access_token)
    if text:
        return text, "genius", None

    return None, None, None
