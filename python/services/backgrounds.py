"""
AI background generation: derive three original image prompts from the
song's title/lyrics (mood-driven, no copyrighted artist/album references),
then render them via the OpenAI Images API.

Note on resolution: neither dall-e-3 nor gpt-image-1 can generate at exactly
1920x1080 (that aspect ratio isn't one of their supported sizes) — we
generate at the widest 16:9-ish size each model supports and let the
"Rendering Karaoke Video" stage's ffmpeg scale/crop step bring it to exactly
1920x1080.
"""

from __future__ import annotations

import base64
from pathlib import Path
from typing import Any

import requests
from openai import OpenAI

from services.status_writer import StatusWriter

FALLBACK_PROMPTS = [
    "A cinematic wide-angle shot of a dramatic mountain landscape at golden hour, "
    "soft warm light, epic clouds, no people, no text, no logos",
    "An abstract flowing field of colorful light trails and bokeh on a dark background, "
    "dreamlike and elegant, no text, no logos",
    "A serene watercolor-style painting of a calm ocean horizon at dusk, soft gradients, "
    "minimalist, no text, no logos",
]

PROMPT_SYSTEM_MESSAGE = (
    "You write concise prompts for an AI image generator that creates ORIGINAL karaoke "
    "video backgrounds. Never reference real artists, bands, album covers, movies, or any "
    "copyrighted imagery or characters. Favor mood-driven, original visuals: cinematic "
    "landscapes, abstract light, watercolor scenes, neon cityscapes, nature, or abstract "
    "textures. Every prompt must explicitly avoid text, logos, and legible words in the image."
)


class BackgroundGenerationError(Exception):
    pass


def generate_prompts(client: OpenAI, title: str, lyrics_excerpt: str, model: str = "gpt-4o-mini") -> list[str]:
    user_message = (
        f"Song title: {title}\n"
        f"Lyrics excerpt: {lyrics_excerpt[:600] or '(no lyrics available)'}\n\n"
        "Write exactly 3 short, visually distinct image prompts (one per line, no numbering "
        "or bullets) capturing this song's mood as original background art for a karaoke video."
    )

    try:
        response = client.chat.completions.create(
            model=model,
            messages=[
                {"role": "system", "content": PROMPT_SYSTEM_MESSAGE},
                {"role": "user", "content": user_message},
            ],
            temperature=0.9,
        )
        text = response.choices[0].message.content or ""
        lines = [line.strip(" -•\t") for line in text.splitlines() if line.strip()]
        prompts = [line for line in lines if len(line) > 15][:3]

        if len(prompts) == 3:
            return prompts
    except Exception:  # noqa: BLE001 - any API hiccup falls back to safe defaults below
        pass

    return FALLBACK_PROMPTS


def _save_image(client: OpenAI, image_data: Any, output_path: Path) -> None:
    b64_payload = getattr(image_data, "b64_json", None)

    if b64_payload:
        output_path.write_bytes(base64.b64decode(b64_payload))
        return

    url = getattr(image_data, "url", None)

    if url:
        response = requests.get(url, timeout=60)
        response.raise_for_status()
        output_path.write_bytes(response.content)
        return

    raise BackgroundGenerationError("OpenAI image response contained neither b64_json nor a url.")


def generate_images(
    config: dict[str, Any],
    status: StatusWriter,
    title: str,
    lyrics_text: str,
) -> list[dict[str, str]]:
    api_key = config.get("openai_api_key") or ""

    if not api_key:
        raise BackgroundGenerationError(
            "OpenAI API key is not configured. Add it on the Settings page before generating backgrounds."
        )

    job_dir = Path(config["job_dir"])
    client = OpenAI(api_key=api_key)

    prompt_model = config.get("prompt_model") or "gpt-4o-mini"
    status.log(f"Analyzing song title and lyrics to write background prompts (model={prompt_model})…")
    prompts = generate_prompts(client, title, lyrics_text, model=prompt_model)

    image_model = config.get("image_model") or "gpt-image-1"
    size = "1536x1024" if "gpt-image" in image_model else "1792x1024"

    results: list[dict[str, str]] = []

    for index, prompt in enumerate(prompts, start=1):
        status.log(f'Generating background {index}/3: "{prompt[:80]}"')

        try:
            response = client.images.generate(model=image_model, prompt=prompt, size=size, n=1)
        except Exception as exc:  # noqa: BLE001
            raise BackgroundGenerationError(f"OpenAI image generation failed: {exc}") from exc

        filename = f"image{index}.png"
        output_path = job_dir / filename
        _save_image(client, response.data[0], output_path)

        results.append({"prompt": prompt, "image_path": filename})
        status.log(f"Saved {filename}")

    return results
