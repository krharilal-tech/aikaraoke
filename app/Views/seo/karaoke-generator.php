<?php
/**
 * @var array $page
 * @var string $slug
 * @var int $maxDurationMinutes
 */

$faqs = [
    [
        'q' => 'What does a karaoke generator do?',
        'a' => 'It produces a karaoke version of a song on demand: the lead vocal is removed, the lyrics are put on screen in time with the music, and the result is packaged as something you can sing along to.',
    ],
    [
        'q' => 'Can it generate karaoke for songs that have no karaoke version?',
        'a' => 'Yes — that’s the main reason to use one. As long as the song is on YouTube and under ' . $maxDurationMinutes . ' minutes, KaraokAI can build a karaoke version from the original recording.',
    ],
    [
        'q' => 'Does it work for Tamil, Malayalam and Hindi songs?',
        'a' => 'Yes. Along with English, these are the languages KaraokAI supports for lyrics. When a song’s lyrics aren’t in any lyrics database, they’re transcribed from the vocals, with a dedicated speech model for Malayalam.',
    ],
    [
        'q' => 'Will the karaoke version sound exactly like the original without vocals?',
        'a' => 'Close, but AI separation isn’t perfect. On most studio recordings the voice is removed cleanly; on some songs faint vocal traces remain, or instruments that sound similar to a voice are partly removed too.',
    ],
    [
        'q' => 'Do I get an audio file or a video?',
        'a' => 'A video: a 1080p MP4 with the instrumental, an AI background and highlighted lyrics. Separate audio-only downloads aren’t offered.',
    ],
    [
        'q' => 'How many karaoke songs can I generate?',
        'a' => 'One credit generates one karaoke video. New accounts get one free credit; more can be bought on the <a href="' . e(base_url('pricing')) . '">pricing page</a>, with no subscription and no expiry.',
    ],
];
?>
<section class="seo-hero">
  <div class="container">
    <?= partial('partials/seo/breadcrumbs', ['page' => $page]) ?>
    <div class="row justify-content-center">
      <div class="col-lg-10">
        <h1 class="hero-title mb-3">AI Karaoke Generator: Create Karaoke Songs and Videos</h1>
        <p class="hero-subtitle mb-4">
          Karaoke catalogues only cover the songs someone has already licensed and re-recorded. KaraokAI generates a
          karaoke version of the song you actually want — from the original recording — and delivers it as a ready-to-sing video.
        </p>
        <a href="<?= e(base_url('/')) ?>#generateCard" class="gradient-btn btn btn-lg">
          <i class="bi bi-magic me-2"></i>Generate Karaoke
        </a>
      </div>
    </div>
  </div>
</section>

<div class="container seo-prose">
  <div class="row justify-content-center">
    <div class="col-lg-10">

      <section class="seo-section" aria-labelledby="what-heading">
        <h2 id="what-heading" class="seo-h2">What Is a Karaoke Generator?</h2>
        <p>
          Traditional karaoke tracks are produced by studios: musicians re-record the backing music, someone types and
          times the lyrics, and the track joins a catalogue. If your song isn’t in that catalogue — an older film song,
          a regional hit, an independent release — there’s simply no karaoke version to sing.
        </p>
        <p>
          A karaoke generator works the other way round. Instead of picking from what exists, you start with any
          recording and generate the karaoke version yourself. AI removes the vocals from the original audio, works out
          the lyrics and their timing, and produces the karaoke track and video in one go.
        </p>
      </section>

      <section class="seo-section" aria-labelledby="how-heading">
        <h2 id="how-heading" class="seo-h2">How KaraokAI Generates Karaoke</h2>
        <div class="glass-card p-4">
          <ol class="flow-strip">
            <?php foreach ([
                ['bi-link-45deg', 'Song input', 'You paste a YouTube link'],
                ['bi-soundwave', 'Vocal separation', 'Demucs isolates the voice'],
                ['bi-music-note-beamed', 'Instrumental track', 'Everything except the vocal'],
                ['bi-card-text', 'Lyrics', 'Lyrics databases or WhisperX transcription'],
                ['bi-clock', 'Synchronization', 'Each word timed to the vocal'],
                ['bi-film', 'Video generation', 'AI background + lyrics → MP4'],
            ] as [$icon, $label, $detail]): ?>
              <li>
                <span class="step-icon"><i class="bi <?= $icon ?>"></i></span>
                <span class="fw-semibold small"><?= e($label) ?></span>
                <span class="flow-detail"><?= e($detail) ?></span>
              </li>
            <?php endforeach; ?>
          </ol>
        </div>
        <p class="mt-3">
          The isolated vocal isn’t discarded: it’s what the lyric transcription and word timing are measured against,
          which is why the highlighting follows the original singer rather than a fixed tempo.
        </p>
      </section>

      <section class="seo-section" aria-labelledby="youtube-heading">
        <h2 id="youtube-heading" class="seo-h2">Create Karaoke from YouTube</h2>
        <div class="glass-card tint-box p-4 d-flex flex-column flex-md-row align-items-md-center gap-3">
          <span class="step-icon"><i class="bi bi-youtube"></i></span>
          <div class="flex-grow-1">
            <p class="mb-2">
              YouTube is where most people already find their songs, so it’s the input KaraokAI uses. Paste the link of
              the song’s video and the generator takes it from there.
            </p>
            <a href="<?= e(base_url('youtube-to-karaoke')) ?>" class="fw-semibold">See the full YouTube-to-karaoke walkthrough <i class="bi bi-arrow-right"></i></a>
          </div>
        </div>
      </section>

      <section class="seo-section" aria-labelledby="video-heading">
        <h2 id="video-heading" class="seo-h2">Create a Karaoke Video</h2>
        <p>
          The generated karaoke always comes as a finished video — 1920×1080, with word-by-word highlighting and AI
          background art — rather than a bare audio file you’d still need to put lyrics on. You can also generate a
          lyric video that keeps the original vocals. Read more about the output on the
          <a href="<?= e(base_url('karaoke-video-maker')) ?>">karaoke video maker</a> page.
        </p>
      </section>

      <section class="seo-section" aria-labelledby="compare-heading">
        <h2 id="compare-heading" class="seo-h2">AI Karaoke vs Traditional Karaoke Tracks</h2>
        <div class="glass-card table-responsive">
          <table class="table compare-table">
            <thead>
              <tr><th scope="col"></th><th scope="col">Traditional karaoke track</th><th scope="col">AI-generated with KaraokAI</th></tr>
            </thead>
            <tbody>
              <tr><th scope="row">Song availability</th><td>Limited to the catalogue</td><td>Any song on YouTube up to <?= $maxDurationMinutes ?> minutes</td></tr>
              <tr><th scope="row">Music</th><td>Studio re-recording of the backing</td><td>The original recording with the vocal removed</td></tr>
              <tr><th scope="row">Audio quality</th><td>Clean, purpose-made stems</td><td>Very good on most songs; faint vocal traces possible</td></tr>
              <tr><th scope="row">Lyrics and timing</th><td>Hand-made by the producer</td><td>Automatic, word-level</td></tr>
              <tr><th scope="row">Regional and older songs</th><td>Often missing</td><td>Supported for Tamil, Malayalam, Hindi and English lyrics</td></tr>
              <tr><th scope="row">Wait time</th><td>Until someone produces it — maybe never</td><td>Generated when you ask</td></tr>
            </tbody>
          </table>
        </div>
      </section>

      <section class="seo-section" aria-labelledby="who-heading">
        <h2 id="who-heading" class="seo-h2">Who Is an AI Karaoke Generator For?</h2>
        <ul class="check-list">
          <li><strong>Fans of regional music</strong> whose favourite Tamil, Malayalam or Hindi songs aren’t in karaoke apps.</li>
          <li><strong>Singers preparing a performance</strong> who need a backing track of one specific recording.</li>
          <li><strong>Families and friend groups</strong> who want the songs they actually know for a karaoke night.</li>
          <li><strong>Choirs and music teachers</strong> making practice material from a reference recording.</li>
          <li><strong>Event hosts</strong> handling song requests that a standard catalogue can’t cover.</li>
        </ul>
        <p>
          New to the idea? The <a href="<?= e(base_url('ai-karaoke-maker')) ?>">AI karaoke maker guide</a> explains each
          stage in more detail, and the <a href="<?= e(base_url('ai-vocal-remover')) ?>">vocal remover</a> page covers
          what to expect from the separation step.
        </p>
      </section>

      <?= partial('partials/seo/faq', ['faqs' => $faqs, 'heading' => 'Frequently Asked Questions']) ?>

      <?= partial('partials/seo/cta', [
          'heading' => 'Generate a Karaoke Version of Your Song',
          'text' => 'Start from the original recording and get a karaoke video you can sing tonight.',
          'button' => 'Generate Karaoke',
      ]) ?>

    </div>
  </div>
</div>

<?= partial('partials/supporting-pages', ['current' => $slug]) ?>
