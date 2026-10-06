<?php
/**
 * @var array $page
 * @var string $slug
 * @var int $maxDurationMinutes
 */

$faqs = [
    [
        'q' => 'How does an AI vocal remover work?',
        'a' => 'It uses a neural network trained on thousands of songs where the vocals and instruments were available separately. Having learned what voices sound like, it can take a finished mix and estimate which parts belong to the vocal and which to the accompaniment, producing two separate tracks.',
    ],
    [
        'q' => 'Which AI model does KaraokAI use?',
        'a' => 'Demucs, an open-source music source-separation model originally developed by Meta AI researchers. KaraokAI runs its Hybrid Transformer model (htdemucs) in two-stem mode: vocals versus everything else.',
    ],
    [
        'q' => 'Can I download just the instrumental audio?',
        'a' => 'Not as a separate file at the moment. KaraokAI delivers the instrumental as the soundtrack of a karaoke video (MP4) with synchronized lyrics on screen.',
    ],
    [
        'q' => 'Will the vocals be removed completely?',
        'a' => 'On most studio recordings, almost completely. Some songs keep faint traces — especially backing harmonies or heavily reverberated vocals — and instruments with a voice-like tone, such as nadaswaram or shehnai, can sometimes be partly removed along with the voice.',
    ],
    [
        'q' => 'Can I remove vocals from any song?',
        'a' => 'Any public YouTube video of a song up to ' . $maxDurationMinutes . ' minutes long, provided you have the right to use it for a personal karaoke track. Uploading your own audio files isn’t supported yet.',
    ],
    [
        'q' => 'Do I need to install anything?',
        'a' => 'No. Separation runs on KaraokAI’s GPU servers; you only need a browser.',
    ],
];
?>
<section class="seo-hero">
  <div class="container">
    <?= partial('partials/seo/breadcrumbs', ['page' => $page]) ?>
    <div class="row justify-content-center">
      <div class="col-lg-10">
        <h1 class="hero-title mb-3">AI Vocal Remover: Create Instrumental Tracks with AI</h1>
        <p class="hero-subtitle mb-4">
          KaraokAI uses AI source separation to pull the vocals out of a finished song, leaving the instrumental —
          then turns it into a karaoke video with the lyrics synced on screen.
        </p>
        <a href="<?= e(base_url('/')) ?>#generateCard" class="gradient-btn btn btn-lg">
          <i class="bi bi-soundwave me-2"></i>Remove Vocals &amp; Make Karaoke
        </a>
      </div>
    </div>
  </div>
</section>

<div class="container seo-prose">
  <div class="row justify-content-center">
    <div class="col-lg-10">

      <section class="seo-section" aria-labelledby="what-heading">
        <h2 id="what-heading" class="seo-h2">What Is an AI Vocal Remover?</h2>
        <p>
          A finished song is a single mixed recording: voice, drums, bass, strings and everything else are blended
          into one waveform. Getting the voice back out was long considered close to impossible. The old trick —
          phase cancellation, which subtracts one stereo channel from the other — only removes sound panned dead
          centre, so it hollows out the bass and drums along with the voice and fails on most modern mixes.
        </p>
        <p>
          An AI vocal remover takes a different approach. A neural network is trained on songs where the vocal and
          instrumental tracks are available separately, until it learns what a voice sounds like in context. Given a
          new song, it predicts which parts of the sound belong to the vocal and which to the accompaniment, and
          rebuilds each as its own track. This is called <strong>source separation</strong>, and the two results are
          known as <strong>stems</strong>.
        </p>
      </section>

      <section class="seo-section" aria-labelledby="how-heading">
        <h2 id="how-heading" class="seo-h2">How KaraokAI Removes Vocals</h2>
        <ol>
          <li class="mb-2"><strong>Audio in.</strong> The song’s audio stream is downloaded from the YouTube link you provide (the video itself isn’t needed).</li>
          <li class="mb-2"><strong>Normalize.</strong> FFmpeg converts it to an uncompressed WAV file so the model gets consistent input.</li>
          <li class="mb-2"><strong>Separate.</strong> <strong>Demucs</strong>, an open-source separation model, runs in two-stem mode on a GPU and outputs a <em>vocals</em> stem and an <em>instrumental</em> stem.</li>
          <li><strong>Use both.</strong> The instrumental becomes the karaoke audio. The vocal stem is used to transcribe and time the lyrics — and is kept in the mix if you choose lyric-video mode.</li>
        </ol>
        <div class="glass-card tint-box p-3 small mt-3">
          <i class="bi bi-info-circle me-1"></i>
          Honest expectations: separation is very good but not perfect. Faint vocal traces can remain on some songs, and
          reedy instruments with a voice-like tone (nadaswaram, shehnai) are occasionally mistaken for vocals.
        </div>
      </section>

      <section class="seo-section" aria-labelledby="flow-heading">
        <h2 id="flow-heading" class="seo-h2">From Vocal Removal to Karaoke Video</h2>
        <p>
          On its own, an instrumental is only half a karaoke track — you still need the words. KaraokAI carries on from
          separation to a finished video:
        </p>
        <div class="glass-card p-4">
          <ol class="flow-strip">
            <?php foreach ([
                ['bi-music-note-beamed', 'Song'],
                ['bi-soundwave', 'Vocal separation'],
                ['bi-headphones', 'Instrumental'],
                ['bi-card-text', 'Lyrics'],
                ['bi-clock', 'Synchronization'],
                ['bi-film', 'Karaoke video'],
            ] as [$icon, $label]): ?>
              <li>
                <span class="step-icon"><i class="bi <?= $icon ?>"></i></span>
                <span class="fw-semibold small"><?= e($label) ?></span>
              </li>
            <?php endforeach; ?>
          </ol>
        </div>
        <p class="mt-3">
          See every stage of this, with what you’ll see on screen, in our guide to
          <a href="<?= e(base_url('youtube-to-karaoke')) ?>">converting YouTube songs to karaoke</a>.
        </p>
      </section>

      <section class="seo-section" aria-labelledby="karaoke-heading">
        <h2 id="karaoke-heading" class="seo-h2">AI Vocal Remover for Karaoke</h2>
        <p>
          Karaoke is where vocal removal matters most. You want the real arrangement — the same intro, the same
          instrumental breaks, the same key — without a second singer competing with you. Because KaraokAI separates
          the original recording, the karaoke version follows the song exactly as you know it, and the lyric highlight
          is timed to the original singer’s phrasing because it’s measured against the removed vocal itself.
        </p>
        <p>
          That combination — the instrumental plus timed lyrics in one video — is what the
          <a href="<?= e(base_url('ai-karaoke-maker')) ?>">AI karaoke maker</a> is built around. If you want a karaoke
          version of a particular song that no catalogue carries, the <a href="<?= e(base_url('karaoke-generator')) ?>">karaoke generator</a>
          page explains that use case, and the <a href="<?= e(base_url('karaoke-video-maker')) ?>">karaoke video maker</a>
          page shows what the finished video includes.
        </p>
      </section>

      <section class="seo-section" aria-labelledby="compare-heading">
        <h2 id="compare-heading" class="seo-h2">AI Vocal Remover vs Traditional Audio Editing</h2>
        <div class="glass-card table-responsive">
          <table class="table compare-table">
            <thead>
              <tr><th scope="col"></th><th scope="col">Traditional editing</th><th scope="col">AI vocal remover (KaraokAI)</th></tr>
            </thead>
            <tbody>
              <tr><th scope="row">Method</th><td>Phase cancellation or EQ cuts</td><td>Neural source separation (Demucs)</td></tr>
              <tr><th scope="row">Effect on the music</th><td>Often thins out bass, drums and anything centred</td><td>Keeps the accompaniment largely intact</td></tr>
              <tr><th scope="row">Works on</th><td>Mainly older stereo mixes with a centred vocal</td><td>Most modern and older recordings</td></tr>
              <tr><th scope="row">Reverb and backing vocals</th><td>Usually left behind</td><td>Mostly removed; faint traces possible</td></tr>
              <tr><th scope="row">Skill and software</th><td>A DAW and some audio knowledge</td><td>A browser and a YouTube link</td></tr>
              <tr><th scope="row">Output</th><td>An audio file you edit further</td><td>A karaoke video with synced lyrics</td></tr>
            </tbody>
          </table>
        </div>
      </section>

      <?= partial('partials/seo/faq', ['faqs' => $faqs, 'heading' => 'Frequently Asked Questions']) ?>

      <?= partial('partials/seo/cta', [
          'heading' => 'Remove the Vocals and Start Singing',
          'text' => 'Paste a YouTube link — KaraokAI separates the vocals and builds the karaoke video for you.',
          'button' => 'Try KaraokAI',
      ]) ?>

    </div>
  </div>
</div>

<?= partial('partials/supporting-pages', ['current' => $slug]) ?>
