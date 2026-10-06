<?php
/**
 * @var array $page
 * @var string $slug
 * @var int $maxDurationMinutes
 */

$faqs = [
    [
        'q' => 'What does a KaraokAI karaoke video look like?',
        'a' => 'It’s a 1920×1080 video with a background image that slowly pans and zooms, the lyrics shown on screen with the current word highlighted as it’s sung, and the instrumental version of the song as the audio.',
    ],
    [
        'q' => 'What resolution and format is the video?',
        'a' => 'Full HD (1920×1080) MP4, using H.264 video and AAC audio, so it plays on smart TVs, laptops, phones and media players without special software.',
    ],
    [
        'q' => 'Can I make a lyric video with the original singer?',
        'a' => 'Yes. Turn on “Keep original vocals” before generating and you get the same synced-lyrics video with the original vocals left in.',
    ],
    [
        'q' => 'Can I edit the lyrics or timing myself?',
        'a' => 'Not inside KaraokAI at the moment — lyrics and timing are produced automatically. Choosing the correct song language before you generate gives the most reliable results.',
    ],
    [
        'q' => 'Can I upload my own background image?',
        'a' => 'Not currently. KaraokAI picks a background image from its own library for each video.',
    ],
    [
        'q' => 'Which languages can the lyrics be in?',
        'a' => 'Tamil, Malayalam, Hindi and English, or you can let KaraokAI auto-detect the language.',
    ],
    [
        'q' => 'Is there a watermark?',
        'a' => 'No. The downloaded MP4 contains your background, lyrics and music only.',
    ],
    [
        'q' => 'How long are videos kept?',
        'a' => 'Seven days after they’re generated. After that the files are deleted automatically, so download the MP4 to keep it.',
    ],
];
?>
<section class="seo-hero">
  <div class="container">
    <?= partial('partials/seo/breadcrumbs', ['page' => $page]) ?>
    <div class="row align-items-center g-5">
      <div class="col-lg-6">
        <h1 class="hero-title mb-3">Karaoke Video Maker: Create Your Own Karaoke Videos</h1>
        <p class="hero-subtitle mb-4">
          Get a finished, full-HD karaoke video — instrumental audio, word-by-word highlighted lyrics and a moving
          background — without opening a video editor.
        </p>
        <a href="<?= e(base_url('/')) ?>#generateCard" class="gradient-btn btn btn-lg">
          <i class="bi bi-film me-2"></i>Create Karaoke Video
        </a>
      </div>
      <div class="col-lg-6">
        <?= partial('partials/seo/screenshot', ['file' => 'karaoke-video-preview.webp', 'alt' => 'A KaraokAI karaoke video frame with the sung word highlighted over the background', 'caption' => 'A finished KaraokAI karaoke video.', 'icon' => 'bi-film']) ?>
      </div>
    </div>
  </div>
</section>

<div class="container seo-prose">
  <div class="row justify-content-center">
    <div class="col-lg-10">

      <section class="seo-section" aria-labelledby="create-heading">
        <h2 id="create-heading" class="seo-h2">Create Karaoke Videos with KaraokAI</h2>
        <p>
          A karaoke video is more than a song with subtitles. The voice needs to be gone, the words need to appear
          a moment before they’re sung, and the highlight has to track the singer closely enough that you can follow
          along. Doing that in a normal video editor means layering an instrumental, building a subtitle track and
          timing each line by ear.
        </p>
        <p>
          KaraokAI builds the whole video for you from a YouTube link. The audio is separated, the lyrics are found
          and timed to the word, a background is added, and everything is rendered into one MP4 file that’s ready
          to play.
        </p>
      </section>

      <section class="seo-section" aria-labelledby="how-heading">
        <h2 id="how-heading" class="seo-h2">How the Karaoke Video Maker Works</h2>
        <div class="row g-3">
          <?php foreach ([
              ['Add your song', 'Paste a YouTube link to the song and, if you know it, choose its language.'],
              ['Remove vocals', 'AI stem separation (Demucs) splits the voice from the music.'],
              ['Generate and synchronize lyrics', 'Lyrics are fetched or transcribed, then each word is timed with WhisperX.'],
              ['Background', 'A background image is picked from our library and applied automatically.'],
              ['Generate the video', 'FFmpeg renders background, highlighted lyrics and audio into a 1080p MP4.'],
              ['Preview and download', 'Watch it in the browser, then download the file.'],
          ] as $index => [$title, $text]): ?>
            <div class="col-sm-6 col-lg-4">
              <div class="glass-card h-100 p-3">
                <div class="d-flex align-items-center gap-2 mb-2">
                  <span class="step-num"><?= $index + 1 ?></span>
                  <h3 class="h6 fw-bold mb-0"><?= e($title) ?></h3>
                </div>
                <p class="text-secondary small mb-0"><?= e($text) ?></p>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <p class="mt-3">
          For a closer walkthrough with what you’ll see at every stage, read
          <a href="<?= e(base_url('youtube-to-karaoke')) ?>">how to turn a YouTube song into karaoke</a>.
        </p>
      </section>

      <section class="seo-section" aria-labelledby="customize-heading">
        <h2 id="customize-heading" class="seo-h2">Customize Your Karaoke Video</h2>
        <p>
          KaraokAI is built to be hands-off, so the options are deliberately few. Here’s what you control and what’s
          handled for you:
        </p>
        <div class="row g-4">
          <div class="col-md-6">
            <div class="glass-card h-100 p-4">
              <h3 class="h6 text-uppercase text-secondary fw-bold mb-3">You choose</h3>
              <ul class="check-list mb-0">
                <li><strong>Song language</strong> — Tamil, Malayalam, Hindi, English or auto-detect. Picking it directly improves lyric accuracy.</li>
                <li><strong>Karaoke or lyric video</strong> — remove the vocals for singing, or keep them for a sing-along lyric video.</li>
              </ul>
            </div>
          </div>
          <div class="col-md-6">
            <div class="glass-card h-100 p-4">
              <h3 class="h6 text-uppercase text-secondary fw-bold mb-3">Done automatically</h3>
              <ul class="check-list mb-0">
                <li><strong>Background image</strong> picked for you, with a slow pan-and-zoom.</li>
                <li><strong>Word-by-word highlighting</strong> timed to the original vocal.</li>
                <li><strong>Full-HD output</strong> at 1920×1080.</li>
              </ul>
            </div>
          </div>
        </div>
      </section>

      <section class="seo-section" aria-labelledby="uses-heading">
        <h2 id="uses-heading" class="seo-h2">Karaoke Videos for Singers, Creators and Events</h2>
        <div class="row g-3">
          <?php foreach ([
              ['bi-mic', 'Singers', 'Practise with the words in front of you and the original arrangement behind you.'],
              ['bi-person-video3', 'Creators', 'A backing video for sing-along or cover content — as long as you have the rights to the music.'],
              ['bi-people', 'Events', 'Build a playlist of karaoke videos before a wedding, party or office event and play them from any laptop.'],
          ] as [$icon, $title, $text]): ?>
            <div class="col-md-4">
              <div class="glass-card h-100 p-3">
                <span class="step-icon mb-2"><i class="bi <?= $icon ?>"></i></span>
                <h3 class="h6 fw-bold"><?= e($title) ?></h3>
                <p class="text-secondary small mb-0"><?= e($text) ?></p>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <p class="mt-3">
          Not sure a video is what you need? The <a href="<?= e(base_url('ai-karaoke-maker')) ?>">AI karaoke maker overview</a>
          explains every part of the process, and the <a href="<?= e(base_url('karaoke-generator')) ?>">karaoke generator</a>
          page compares AI-made karaoke with traditional tracks.
        </p>
      </section>

      <section class="seo-section" aria-labelledby="compare-heading">
        <h2 id="compare-heading" class="seo-h2">Karaoke Video Maker vs Traditional Video Editing</h2>
        <div class="glass-card table-responsive">
          <table class="table compare-table">
            <thead>
              <tr><th scope="col"></th><th scope="col">Video editor</th><th scope="col">KaraokAI</th></tr>
            </thead>
            <tbody>
              <tr><th scope="row">Instrumental</th><td>Source or create it separately</td><td>Separated from the song automatically</td></tr>
              <tr><th scope="row">Lyrics on screen</th><td>Type them into text layers or a subtitle file</td><td>Fetched or transcribed for you</td></tr>
              <tr><th scope="row">Word highlighting</th><td>Keyframe or time each word manually</td><td>Automatic, from forced alignment</td></tr>
              <tr><th scope="row">Background</th><td>Find footage or design one</td><td>Added automatically</td></tr>
              <tr><th scope="row">Export</th><td>Pick codecs and settings yourself</td><td>1080p MP4, ready to play</td></tr>
              <tr><th scope="row">Creative control</th><td>Unlimited — fonts, effects, layout</td><td>Fixed style; language and mode only</td></tr>
              <tr><th scope="row">Skills needed</th><td>Video editing experience</td><td>None</td></tr>
            </tbody>
          </table>
        </div>
      </section>

      <section class="seo-section" aria-labelledby="formats-heading">
        <h2 id="formats-heading" class="seo-h2">Supported Formats</h2>
        <div class="glass-card table-responsive">
          <table class="table compare-table">
            <tbody>
              <tr><th scope="row">Song input</th><td>YouTube link (youtube.com, youtu.be, Shorts, YouTube Music), up to <?= $maxDurationMinutes ?> minutes</td></tr>
              <tr><th scope="row">Video output</th><td>MP4 · 1920×1080 · H.264 video · AAC audio</td></tr>
              <tr><th scope="row">Lyrics languages</th><td>Tamil, Malayalam, Hindi, English, auto-detect</td></tr>
            </tbody>
          </table>
        </div>
        <p class="mt-3 small text-secondary">
          Uploading your own audio or video files isn’t supported. Videos are for personal use — see our
          <a href="<?= e(base_url('terms')) ?>">Terms of Service</a> on using copyrighted songs.
        </p>
      </section>

      <?= partial('partials/seo/faq', ['faqs' => $faqs, 'heading' => 'Frequently Asked Questions']) ?>

      <p class="mt-4">
        Only need the music without the voice? Here’s <a href="<?= e(base_url('ai-vocal-remover')) ?>">how our AI vocal remover works</a>.
      </p>

      <?= partial('partials/seo/cta', [
          'heading' => 'Create Your Karaoke Video with KaraokAI',
          'text' => 'One YouTube link in, one full-HD karaoke video out.',
          'button' => 'Create Karaoke Video',
      ]) ?>

    </div>
  </div>
</div>

<?= partial('partials/supporting-pages', ['current' => $slug]) ?>
