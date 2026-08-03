# Local Background Images

Drop your own background images here (`.png`, `.jpg`, `.jpeg`, or `.webp`)
and set **Settings → Background Source** to **Local Folder** to use them
instead of generating new ones with the OpenAI Images API for every job —
no OpenAI key or image-generation cost required.

Any resolution works — the render stage upscales/crops whatever you provide
to 1920×1080 with the same Ken Burns pan/zoom used for AI-generated
backgrounds. Widescreen (16:9-ish) images look best; a tall portrait image
will get cropped more aggressively.

Each job that uses this mode picks 3 images at random from whatever is in
this folder (reusing images if you have fewer than 3) and offers them on
the background-selection step exactly like the 3 AI-generated options
normally shown.
