<?php
/**
 * @var array $page
 * @var string $slug
 * @var int $maxDurationMinutes
 */

$faqs = [
    [
        'q' => 'What is an AI karaoke maker?',
        'a' => 'It’s a tool that turns an ordinary song into a karaoke track or video using AI: a source-separation model removes the vocals, speech recognition finds and times the lyrics, and the result is rendered as a video you can sing along to.',
    ],
    [
        'q' => 'Can KaraokAI turn a YouTube song into karaoke?',
        'a' => 'Yes — that’s how it works. You paste a YouTube link and KaraokAI does the rest. The <a href="' . e(base_url('youtube-to-karaoke')) . '">YouTube to karaoke guide</a> walks through each step with what to expect.',
    ],
    [
        'q' => 'Can AI remove vocals from a song?',
        'a' => 'Yes. KaraokAI uses Demucs, an AI model trained to separate a mixed recording into vocals and accompaniment. It works well on most studio recordings, though faint traces of the voice can remain on some songs, and instruments with a very voice-like tone can occasionally be treated as vocals.',
    ],
    [
        'q' => 'Can KaraokAI synchronize lyrics automatically?',
        'a' => 'Yes. Lyrics are fetched from lyrics databases or transcribed from the vocals, then WhisperX aligns each word to the moment it’s sung. The video highlights lyrics word by word.',
    ],
    [
        'q' => 'Can I create a karaoke video?',
        'a' => 'Yes. The output is always a finished karaoke video — a 1920×1080 MP4 with the instrumental, a background and highlighted lyrics — not just an audio file.',
    ],
    [
        'q' => 'Can I choose the background?',
        'a' => 'Not manually at the moment. KaraokAI generates original AI artwork from the song’s title and lyrics and uses it automatically.',
    ],
    [
        'q' => 'Can I preview the karaoke video?',
        'a' => 'Yes. The finished video plays in your browser on the job page before you download it.',
    ],
    [
        'q' => 'What audio or video formats are supported?',
        'a' => 'Input is a YouTube link (regular, youtu.be, Shorts or YouTube Music) for a song up to ' . $maxDurationMinutes . ' minutes long; uploading your own audio files isn’t supported yet. Output is an MP4 video (1080p, H.264 with AAC audio).',
    ],
];
?>
<section class="seo-hero">
  <div class="container">
    <?= partial('partials/seo/breadcrumbs', ['page' => $page]) ?>
    <div class="row justify-content-center">
      <div class="col-lg-10">
        <h1 class="hero-title mb-3">AI Karaoke Maker: Create Karaoke Videos with AI</h1>
        <p class="hero-subtitle mb-4">
          Making karaoke used to mean hunting for a backing track, fiddling with audio software to strip out the
          voice, and timing every lyric line by hand. KaraokAI’s AI karaoke maker does all three for you and hands
          back a finished karaoke video.
        </p>
        <a href="<?= e(base_url('/')) ?>#generateCard" class="gradient-btn btn btn-lg">
          <i class="bi bi-magic me-2"></i>Try KaraokAI
        </a>
      </div>
    </div>
  </div>
</section>

<div class="container seo-prose">
  <div class="row justify-content-center">
    <div class="col-lg-10">

      <section class="seo-section" aria-labelledby="what-heading">
        <h2 id="what-heading" class="seo-h2">What Is an AI Karaoke Maker?</h2>
        <p>
          A karaoke video needs four ingredients: music without the lead vocal, the lyrics, the timing of those lyrics,
          and something to look at. Traditionally a person produces each of these by hand. An AI karaoke maker uses
          machine-learning models to produce them from the original song instead:
        </p>
        <ul class="check-list">
          <li><strong>Vocal separation</strong> — a model listens to the full mix and splits it into a vocal track and an instrumental (background music) track.</li>
          <li><strong>Lyrics</strong> — found in a lyrics database, or transcribed from the isolated vocals with speech recognition.</li>
          <li><strong>Lyric synchronization</strong> — each word is aligned to the exact moment it’s sung, so the highlight moves with the singer.</li>
          <li><strong>Video generation</strong> — the instrumental, the timed lyrics and a background are rendered into one video file.</li>
          <li><strong>Background</strong> — instead of a stock loop, KaraokAI generates original artwork based on the song.</li>
        </ul>
        <p>
          The result is a sing-along version of the exact recording you love, rather than a re-recorded cover.
        </p>
      </section>

      <section class="seo-section" aria-labelledby="create-heading">
        <h2 id="create-heading" class="seo-h2">Create a Karaoke Video with KaraokAI</h2>
        <p>The whole workflow, from your side and ours:</p>
        <div class="row g-4">
          <div class="col-md-5">
            <div class="glass-card h-100 p-4">
              <h3 class="h6 text-uppercase text-secondary fw-bold mb-3">What you do</h3>
              <ol class="mb-0">
                <li class="mb-2">Enter a YouTube URL</li>
                <li class="mb-2">Optionally choose the song’s language</li>
                <li>Preview and download the video</li>
              </ol>
            </div>
          </div>
          <div class="col-md-7">
            <div class="glass-card h-100 p-4">
              <h3 class="h6 text-uppercase text-secondary fw-bold mb-3">What KaraokAI does</h3>
              <ol class="mb-0">
                <li class="mb-2">Processes the song’s audio</li>
                <li class="mb-2">Separates vocals and instrumental music</li>
                <li class="mb-2">Finds or generates the lyrics and synchronizes them</li>
                <li class="mb-2">Creates a background for the video</li>
                <li>Generates the karaoke video</li>
              </ol>
            </div>
          </div>
        </div>
        <div class="row g-4 mt-1">
          <div class="col-md-6">
            <?= partial('partials/seo/screenshot', ['file' => 'youtube-url-input.webp', 'alt' => 'KaraokAI generator form with the YouTube URL field and Generate Karaoke button', 'caption' => 'Start with a YouTube link.', 'icon' => 'bi-link-45deg']) ?>
          </div>
          <div class="col-md-6">
            <?= partial('partials/seo/screenshot', ['file' => 'karaoke-video-preview.webp', 'alt' => 'Finished karaoke video with highlighted lyrics in the KaraokAI player', 'caption' => 'Finish with a karaoke video you can preview and download.', 'icon' => 'bi-play-btn']) ?>
          </div>
        </div>
      </section>

      <section class="seo-section" aria-labelledby="works-heading">
        <h2 id="works-heading" class="seo-h2">How KaraokAI’s AI Karaoke Maker Works</h2>

        <h3 class="mt-4">Add Your Song</h3>
        <p>
          You give KaraokAI a YouTube link to the song (up to <?= $maxDurationMinutes ?> minutes). Only the audio is
          downloaded — the original video isn’t used. Picking the language (Tamil, Malayalam, Hindi or English) helps
          the lyric stages; auto-detect is there if you’re unsure.
        </p>

        <h3 class="mt-4">Separate Vocals and Music</h3>
        <p>
          The audio is normalized with FFmpeg and passed to <strong>Demucs</strong>, an open-source source-separation
          model, which produces two stems: vocals and everything else. The “everything else” stem becomes your karaoke
          track. Our <a href="<?= e(base_url('ai-vocal-remover')) ?>">AI vocal remover</a> page goes deeper into this step.
        </p>

        <h3 class="mt-4">Generate and Synchronize Lyrics</h3>
        <p>
          KaraokAI looks the song up in LRCLIB, Musixmatch and Genius. If none of them has it — common for regional
          and independent music — it transcribes the vocal stem with <strong>WhisperX</strong>, with a dedicated
          model for Malayalam. WhisperX then performs forced alignment: matching each written word to the audio so it
          gets its own start and end time.
        </p>

        <h3 class="mt-4">Get a Background</h3>
        <p>
          An AI model reads the song’s title and lyrics, writes descriptions of scenes that fit the mood, and an image
          model turns them into original artwork. The first image is used automatically, so there’s no waiting step.
        </p>

        <h3 class="mt-4">Generate the Karaoke Video</h3>
        <p>
          FFmpeg renders the background with a slow pan-and-zoom, burns in the lyrics with word-by-word highlighting,
          and lays the instrumental underneath, producing a 1920×1080 MP4.
        </p>

        <h3 class="mt-4">Preview and Download</h3>
        <p>
          A progress page shows each stage live; we also email you when it’s done. Play the video in the browser, then
          download it. Videos stay available for 7 days.
        </p>
      </section>

      <section class="seo-section" aria-labelledby="why-heading">
        <h2 id="why-heading" class="seo-h2">Why Use an AI Karaoke Maker?</h2>
        <div class="row g-3">
          <?php foreach ([
              ['bi-clock', 'Minutes, not an afternoon', 'The steps that take longest by hand — vocal removal and lyric timing — are automated.'],
              ['bi-cpu', 'No audio software', 'Nothing to install or learn. It runs in the browser; the heavy work happens on our GPU servers.'],
              ['bi-music-note-beamed', 'The original arrangement', 'You sing over the real recording, not a cover — useful for songs that were never released as karaoke.'],
              ['bi-card-text', 'Timing done for you', 'Word-level alignment instead of nudging subtitle timestamps one by one.'],
              ['bi-image', 'A background that fits', 'Artwork generated from the song itself rather than a generic animation.'],
              ['bi-people', 'Simple enough for anyone', 'If you can copy a YouTube link, you can make a karaoke video.'],
          ] as [$icon, $title, $text]): ?>
            <div class="col-sm-6 col-lg-4">
              <div class="glass-card h-100 p-3">
                <span class="step-icon mb-2"><i class="bi <?= $icon ?>"></i></span>
                <h3 class="h6 fw-bold"><?= e($title) ?></h3>
                <p class="text-secondary small mb-0"><?= e($text) ?></p>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </section>

      <section class="seo-section" aria-labelledby="compare-heading">
        <h2 id="compare-heading" class="seo-h2">AI Karaoke Maker vs Traditional Karaoke Creation</h2>
        <div class="glass-card table-responsive">
          <table class="table compare-table">
            <thead>
              <tr><th scope="col"></th><th scope="col">Traditional / manual</th><th scope="col">KaraokAI workflow</th></tr>
            </thead>
            <tbody>
              <tr><th scope="row">Vocal removal</th><td>Phase-cancellation tricks in an audio editor, or finding a separate backing track</td><td>Automatic AI stem separation (Demucs)</td></tr>
              <tr><th scope="row">Lyric timing</th><td>Typed and timed line by line in a subtitle editor</td><td>Found or transcribed, then aligned word by word</td></tr>
              <tr><th scope="row">Video editing</th><td>Assembled in a video editor, then exported</td><td>Rendered automatically to MP4</td></tr>
              <tr><th scope="row">Background</th><td>Sourced or designed separately</td><td>Generated by AI from the song</td></tr>
              <tr><th scope="row">Processing time</th><td>Often hours of manual work per song</td><td>Automated; depends on song length and server load</td></tr>
              <tr><th scope="row">Technical skill</th><td>Audio and video editing experience</td><td>None — paste a link</td></tr>
              <tr><th scope="row">Control</th><td>Full control over every detail</td><td>Language and karaoke/lyric-video mode; the rest is automatic</td></tr>
            </tbody>
          </table>
        </div>
        <p class="mt-3 small text-secondary">
          Manual production still gives you more control. The AI approach trades that for speed and works for songs that
          have no karaoke version at all.
        </p>
      </section>

      <section class="seo-section" aria-labelledby="who-heading">
        <h2 id="who-heading" class="seo-h2">Who Can Use KaraokAI?</h2>
        <ul class="check-list">
          <li><strong>Karaoke singers</strong> who want songs that aren’t in any karaoke catalogue.</li>
          <li><strong>Home users</strong> setting up a karaoke night on the living-room TV.</li>
          <li><strong>Musicians and vocal students</strong> who need an instrumental with the words on screen to practise with.</li>
          <li><strong>Karaoke hosts and DJs</strong> preparing requested songs ahead of an event.</li>
          <li><strong>YouTube creators</strong> making sing-along content (with the appropriate rights to the music).</li>
          <li><strong>Party and event organizers</strong> — weddings, office parties, family get-togethers.</li>
        </ul>
        <p>
          If you already know which song you want, the quickest route is
          <a href="<?= e(base_url('youtube-to-karaoke')) ?>">turning that YouTube song into karaoke</a> directly. If you
          care most about the finished video, see the <a href="<?= e(base_url('karaoke-video-maker')) ?>">karaoke video maker</a>,
          or compare approaches on the <a href="<?= e(base_url('karaoke-generator')) ?>">karaoke generator</a> page.
        </p>
      </section>

      <?= partial('partials/seo/faq', ['faqs' => $faqs, 'heading' => 'Frequently Asked Questions']) ?>

      <?= partial('partials/seo/cta', [
          'heading' => 'Create Your Karaoke Video',
          'text' => 'Paste a YouTube link and let the AI karaoke maker handle the vocals, lyrics and video.',
          'button' => 'Try KaraokAI',
      ]) ?>

    </div>
  </div>
</div>

<?= partial('partials/supporting-pages', ['current' => $slug]) ?>
