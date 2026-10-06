<?php

declare(strict_types=1);

/**
 * Registry for the SEO landing pages (see SeoPageController). The same
 * entries drive each page's <head> metadata, the "Explore KaraokAI" cards
 * (Views/partials/supporting-pages.php) and their order, so adding or
 * renaming a page only happens here. Remember to mirror any path change in
 * public/sitemap.xml.
 */
return [
    'brand' => 'KaraokAI',

    'pages' => [
        'ai-karaoke-maker' => [
            'path' => 'ai-karaoke-maker',
            'name' => 'AI Karaoke Maker',
            'blurb' => 'Create karaoke videos with AI.',
            'icon' => 'bi-mic',
            'title' => 'AI Karaoke Maker – Create Karaoke Videos with AI | KaraokAI',
            'description' => 'Create karaoke videos with AI using KaraokAI. Turn songs into karaoke tracks with separated vocals, synchronized lyrics and AI-generated backgrounds.',
        ],
        'youtube-to-karaoke' => [
            'path' => 'youtube-to-karaoke',
            'name' => 'YouTube to Karaoke',
            'blurb' => 'Turn a YouTube song into a karaoke video.',
            'icon' => 'bi-youtube',
            'title' => 'YouTube to Karaoke – Turn Any Song Into a Karaoke Video | KaraokAI',
            'description' => 'Turn a YouTube song into a karaoke video with KaraokAI. Separate vocals, generate synchronized lyrics and add an AI background to create your karaoke video.',
        ],
        'karaoke-video-maker' => [
            'path' => 'karaoke-video-maker',
            'name' => 'Karaoke Video Maker',
            'blurb' => 'Create karaoke videos with synchronized lyrics.',
            'icon' => 'bi-film',
            'title' => 'Karaoke Video Maker – Create Karaoke Videos with AI | KaraokAI',
            'description' => 'Create karaoke videos with AI using KaraokAI. Add synchronized lyrics, instrumental music and AI-generated backgrounds to create your own karaoke videos.',
        ],
        'karaoke-generator' => [
            'path' => 'karaoke-generator',
            'name' => 'Karaoke Generator',
            'blurb' => 'Generate karaoke songs and videos with AI.',
            'icon' => 'bi-magic',
            'title' => 'Karaoke Generator – Generate Karaoke Songs with AI | KaraokAI',
            'description' => 'Generate karaoke songs and videos with KaraokAI. Use AI to separate vocals, synchronize lyrics and create ready-to-sing karaoke videos.',
        ],
        'ai-vocal-remover' => [
            'path' => 'ai-vocal-remover',
            'name' => 'AI Vocal Remover',
            'blurb' => 'Separate vocals and instrumental music using AI.',
            'icon' => 'bi-soundwave',
            'title' => 'AI Vocal Remover – Remove Vocals from Songs with AI | KaraokAI',
            'description' => 'Remove vocals from songs with AI using KaraokAI. Create instrumental tracks for karaoke and generate synchronized karaoke videos from your music.',
        ],
    ],
];
