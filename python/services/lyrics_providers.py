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
from typing import Any

import requests

USER_AGENT = "AIKaraokeMaker/1.0 (+https://github.com/)"


def guess_artist_title(youtube_title: str, channel: str) -> tuple[str, str]:
    """YouTube gives us one title string and a channel name, not clean
    artist/track fields — this is a best-effort heuristic, not a guarantee."""

    cleaned = re.sub(
        r"[\(\[][^\)\]]*\b(official|video|audio|lyrics?|hd|4k|remaster\w*|visualizer|mv)\b[^\)\]]*[\)\]]",
        "",
        youtube_title,
        flags=re.IGNORECASE,
    ).strip(" -|")

    for separator in (" - ", " – ", " — "):
        if separator in cleaned:
            artist, _, title = cleaned.partition(separator)
            return artist.strip(), title.strip()

    if "|" in cleaned:
        # Common convention for regional-language uploads, e.g.
        # "Kanchimmiyo | Cover Song Lyrics | KS Harishankar" — the real
        # song title is just the first segment; everything after the
        # first "|" is tagging noise that would otherwise sink a lyrics
        # provider search (no exact match on the full messy string).
        title = cleaned.split("|", 1)[0].strip()
        if title:
            return channel.strip(), title

    return channel.strip(), cleaned.strip()


def fetch_lrclib(artist: str, title: str, duration_sec: int | None) -> str | None:
    try:
        params = {"track_name": title, "artist_name": artist}
        if duration_sec:
            params["duration"] = duration_sec

        response = requests.get(
            "https://lrclib.net/api/get",
            params=params,
            headers={"User-Agent": USER_AGENT},
            timeout=10,
        )

        if response.status_code == 200:
            data = response.json()
            text = data.get("plainLyrics") or data.get("syncedLyrics")
            if text:
                return _strip_lrc_timestamps(text)

        # Fall back to search when there's no exact match.
        response = requests.get(
            "https://lrclib.net/api/search",
            params={"q": f"{artist} {title}"},
            headers={"User-Agent": USER_AGENT},
            timeout=10,
        )

        if response.status_code == 200:
            results = response.json()
            if isinstance(results, list) and results:
                text = results[0].get("plainLyrics") or results[0].get("syncedLyrics")
                if text:
                    return _strip_lrc_timestamps(text)
    except (requests.RequestException, ValueError):
        return None

    return None


def _strip_lrc_timestamps(text: str) -> str:
    """LRC "synced" lines look like "[00:12.34]word word word" — strip the
    tag since we only use this as plain text for forced alignment."""

    lines = [re.sub(r"^\[\d{2}:\d{2}(\.\d{1,2})?\]\s*", "", line) for line in text.splitlines()]
    return "\n".join(line for line in lines if line.strip())


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
) -> tuple[str | None, str | None]:
    """Returns (text, source) from the first provider that has a match."""

    text = fetch_lrclib(artist, title, duration_sec)
    if text:
        return text, "lrclib"

    text = fetch_musixmatch(artist, title, musixmatch_api_key)
    if text:
        return text, "musixmatch"

    text = fetch_genius(artist, title, genius_access_token)
    if text:
        return text, "genius"

    return None, None
