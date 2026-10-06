<?php
/**
 * @var array $page
 * @var string $slug
 * @var int $maxDurationMinutes
 */
use App\Services\SeoSchema;

$steps = [
    [
        'title' => 'Paste Your YouTube URL',
        'text' => 'Copy the link of the song from YouTube and paste it into the KaraokAI form. Regular video links (youtube.com/watch), short links (youtu.be), Shorts and YouTube Music links all work. Optionally pick the song’s language, then press Generate Karaoke.',
        'shot' => ['youtube-url-input.webp', 'KaraokAI generator form with the YouTube URL field, song language menu and Generate Karaoke button', 'The generator form: paste a link, pick a language, generate.', 'bi-youtube'],
    ],
    [
        'title' => 'Separate Vocals and Music with AI',
        'text' => 'KaraokAI downloads only the audio, converts it to a clean WAV file and runs it through Demucs, an AI source-separation model that splits the recording into a vocal track and an instrumental track.',
        'shot' => ['processing-vocal-separation.webp', 'KaraokAI progress page showing the Removing Vocals stage in progress', 'The progress page shows each stage as it runs.', 'bi-soundwave'],
    ],
    [
        'title' => 'Generate and Synchronize Lyrics',
        'text' => 'The lyrics are looked up in public lyrics databases first. If no match is found, KaraokAI transcribes them straight from the isolated vocals with WhisperX. Either way, WhisperX then aligns every word to the moment it is sung.',
        'shot' => ['lyrics-synchronized.webp', 'Karaoke video frame with the current lyric line highlighted word by word', 'Lyrics are timed word by word, not just line by line.', 'bi-card-text'],
    ],
    [
        'title' => 'Get a Video Background',
        'text' => 'KaraokAI picks a background image for your video from its own library of backgrounds. It happens automatically, so rendering starts without waiting on you.',
        'shot' => ['video-background.webp', 'Background image used behind the lyrics in a KaraokAI karaoke video', 'A background is picked automatically for each video.', 'bi-image'],
    ],
    [
        'title' => 'Generate Your Karaoke Video',
        'text' => 'FFmpeg combines the instrumental track, the background (with a slow pan-and-zoom so it isn’t a static frame) and the word-highlighted lyrics into a 1920×1080 MP4. You can close the page — we email you when it’s ready.',
        'shot' => ['rendering-video.webp', 'KaraokAI progress page during the Rendering Karaoke Video stage', 'Rendering is the last stage; an estimated time is shown while it runs.', 'bi-film'],
    ],
    [
        'title' => 'Preview and Download',
        'text' => 'Watch the finished karaoke video right in your browser, then download the MP4. Your videos are listed under My Videos and kept for 7 days.',
        'shot' => ['karaoke-video-preview.webp', 'Finished karaoke video playing in the KaraokAI preview player with a Download MP4 button', 'Preview in the browser, then download the MP4.', 'bi-play-btn'],
    ],
];

$faqs = [
    [
        'q' => 'Can I convert any YouTube song to karaoke?',
        'a' => 'You can paste any public YouTube video link up to ' . $maxDurationMinutes . ' minutes long. Private or restricted videos can’t be processed. You should only convert songs you have the right to use for a personal karaoke track — see our <a href="' . e(base_url('terms')) . '">Terms of Service</a>. Results are best on clean studio recordings; live recordings with heavy crowd noise or reverb are harder for any vocal remover.',
    ],
    [
        'q' => 'How does YouTube-to-karaoke conversion work?',
        'a' => 'KaraokAI downloads the audio of the video, separates the vocals from the music with Demucs, finds or transcribes the lyrics, times every word with WhisperX, adds a background image, and renders everything into a karaoke video with FFmpeg. Each stage is shown live on the progress page.',
    ],
    [
        'q' => 'Does KaraokAI remove vocals automatically?',
        'a' => 'Yes. Vocal removal is fully automatic — there are no settings to tweak. If you’d rather keep the singer’s voice and just get a lyric video, switch on “Keep original vocals” before generating. More on how separation works on the <a href="' . e(base_url('ai-vocal-remover')) . '">AI vocal remover</a> page.',
    ],
    [
        'q' => 'Can KaraokAI generate lyrics?',
        'a' => 'Yes. It first searches lyrics databases (LRCLIB, Musixmatch and Genius). If none has the song, it transcribes the lyrics directly from the isolated vocals using WhisperX, so songs without published lyrics still get them.',
    ],
    [
        'q' => 'Can lyrics be synchronized with the music?',
        'a' => 'Yes. WhisperX forced alignment matches each word to the point in the vocal track where it is sung, and the video highlights the lyrics word by word. Timing is usually very close; fast or heavily layered vocals can occasionally drift slightly.',
    ],
    [
        'q' => 'Can I choose a background?',
        'a' => 'Not right now. KaraokAI picks a background image from its own library automatically, which keeps the whole process hands-free.',
    ],
    [
        'q' => 'Can I preview the result?',
        'a' => 'Yes. When processing finishes, the video plays directly on the job page so you can check it before downloading.',
    ],
    [
        'q' => 'Can I download the karaoke video?',
        'a' => 'Yes, as a 1080p MP4 file. Generated videos are kept for 7 days and then deleted automatically, so download any you want to keep.',
    ],
    [
        'q' => 'How long does processing take?',
        'a' => 'It depends on the length of the song and how busy our GPU servers are. The progress page shows an estimated time remaining, and we email you when your video is ready so you don’t have to keep the page open.',
    ],
    [
        'q' => 'Is KaraokAI free?',
        'a' => 'Every new account gets one free karaoke video. After that you buy credits — one credit makes one video, with no subscription, and credits don’t expire. If a video fails because of a problem on our side, the credit is refunded automatically. See <a href="' . e(base_url('pricing')) . '">pricing</a>.',
    ],
];
?>
<section class="seo-hero">
  <div class="container">
    <?= partial('partials/seo/breadcrumbs', ['page' => $page]) ?>
    <div class="row align-items-center g-5">
      <div class="col-lg-6">
        <span class="badge rounded-pill badge-tint mb-3 px-3 py-2">
          <i class="bi bi-youtube me-1"></i> YouTube karaoke maker
        </span>
        <h1 class="hero-title mb-3">YouTube to Karaoke: Turn Any Song Into a Karaoke Video</h1>
        <p class="hero-subtitle mb-4">
          Turn a YouTube song into a karaoke video with AI. Paste a link and KaraokAI removes the vocals,
          syncs the lyrics word by word, adds a video background and hands you a ready-to-sing MP4.
        </p>
        <div class="d-flex flex-wrap gap-2 mb-3">
          <a href="<?= e(base_url('/')) ?>#generateCard" class="gradient-btn btn btn-lg">
            <i class="bi bi-magic me-2"></i>Create Karaoke Video
          </a>
          <a href="#how-it-works" class="btn btn-lg btn-outline-secondary">How it works</a>
        </div>
        <p class="text-secondary small mb-0">Free account includes 1 karaoke video. Songs up to <?= $maxDurationMinutes ?> minutes.</p>
      </div>
      <div class="col-lg-6">
        <?= partial('partials/seo/demo-video') ?>
      </div>
    </div>
  </div>
</section>

<div class="container seo-prose">
  <div class="row justify-content-center">
    <div class="col-lg-10">

      <section class="seo-section" id="how-it-works" aria-labelledby="how-heading">
        <h2 id="how-heading" class="seo-h2">How to Turn YouTube Songs Into Karaoke</h2>
        <p class="text-secondary">
          Converting YouTube to karaoke takes six steps, and you only do the first one yourself. Everything
          after the link is handled automatically.
        </p>

        <?php foreach ($steps as $index => $step): ?>
          <?php [$file, $alt, $caption, $icon] = $step['shot']; ?>
          <div class="row align-items-center g-4 mt-2 mb-4" id="step-<?= $index + 1 ?>">
            <div class="col-md-6 <?= $index % 2 === 1 ? 'order-md-2' : '' ?>">
              <div class="d-flex align-items-center gap-2 mb-2">
                <span class="step-num"><?= $index + 1 ?></span>
                <h3 class="mb-0"><?= $index + 1 ?>. <?= e($step['title']) ?></h3>
              </div>
              <p class="mb-0"><?= e($step['text']) ?></p>
            </div>
            <div class="col-md-6">
              <?= partial('partials/seo/screenshot', ['file' => $file, 'alt' => $alt, 'caption' => $caption, 'icon' => $icon]) ?>
            </div>
          </div>
        <?php endforeach; ?>
      </section>

      <section class="seo-section" aria-labelledby="why-heading">
        <h2 id="why-heading" class="seo-h2">Why Use KaraokAI for YouTube to Karaoke?</h2>
        <p>
          Most songs you want to sing already exist on YouTube — but a karaoke version of that exact recording
          often doesn’t. KaraokAI builds one from the original, so you sing over the arrangement you actually know.
        </p>
        <div class="row g-3">
          <?php foreach ([
              ['bi-link-45deg', 'One link, no editing', 'Paste a URL and you’re done. No audio editor, no video timeline, no subtitle files.'],
              ['bi-soundwave', 'AI vocal separation', 'Demucs splits voice from music, so the instrumental keeps the original arrangement.'],
              ['bi-card-text', 'Word-level lyric sync', 'Lyrics are found or transcribed, then each word is timed to the vocal track.'],
              ['bi-translate', 'Indian-language support', 'Tamil, Malayalam, Hindi and English, with auto-detect if you’re not sure.'],
              ['bi-image', 'A moving background', 'A background image with a slow pan-and-zoom, so the video never sits on a static frame.'],
              ['bi-file-earmark-play', 'Ready-to-play MP4', 'A 1080p file that plays on a TV, laptop or phone without any karaoke software.'],
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

      <section class="seo-section" aria-labelledby="pipeline-heading">
        <h2 id="pipeline-heading" class="seo-h2">What Happens When You Convert YouTube to Karaoke?</h2>
        <p>
          Behind the single button is a seven-stage pipeline running on GPU servers. These are the same stages
          you’ll see ticking off on the progress page.
        </p>
        <div class="glass-card p-4">
          <ol class="flow-strip">
            <?php foreach ([
                ['bi-youtube', 'YouTube song', 'Only the audio stream is downloaded'],
                ['bi-music-note-beamed', 'Audio extraction', 'Converted to clean WAV with FFmpeg'],
                ['bi-soundwave', 'Vocal separation', 'Demucs splits vocals and instrumental'],
                ['bi-card-text', 'Lyrics', 'Lyrics databases, or WhisperX transcription'],
                ['bi-clock', 'Synchronization', 'WhisperX times each word'],
                ['bi-image', 'Background', 'Picked automatically from our library'],
                ['bi-film', 'Karaoke video', '1080p MP4 rendered with FFmpeg'],
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
          The vocal track isn’t thrown away: it’s what the lyric timing is measured against, and it’s mixed back in
          if you choose to keep the original vocals. If you’re curious about the separation step in particular,
          read <a href="<?= e(base_url('ai-vocal-remover')) ?>">how AI vocal removal works</a>.
        </p>
      </section>

      <section class="seo-section" aria-labelledby="compare-heading">
        <h2 id="compare-heading" class="seo-h2">YouTube to Karaoke vs Traditional Karaoke</h2>
        <p>
          The traditional route is to search for a karaoke track someone else already made. That works for popular
          hits, but here’s how it compares for everything else.
        </p>
        <div class="glass-card table-responsive">
          <table class="table compare-table">
            <thead>
              <tr><th scope="col"></th><th scope="col">Traditional karaoke track</th><th scope="col">YouTube to karaoke with KaraokAI</th></tr>
            </thead>
            <tbody>
              <tr><th scope="row">Finding a track</th><td>Only works if someone already made one for that song</td><td>Start from any YouTube upload of the song</td></tr>
              <tr><th scope="row">Vocal removal</th><td>Re-recorded backing track, often a cover version</td><td>AI separation of the original recording</td></tr>
              <tr><th scope="row">Lyrics</th><td>Typed in by the track’s creator</td><td>Looked up automatically, or transcribed from the vocals</td></tr>
              <tr><th scope="row">Synchronization</th><td>Timed by hand, quality varies</td><td>Word-level timing from forced alignment</td></tr>
              <tr><th scope="row">Video creation</th><td>Fixed — you get what was uploaded</td><td>Rendered fresh for every song</td></tr>
              <tr><th scope="row">Background</th><td>Usually a generic loop or plain colour</td><td>Added automatically, with a slow pan-and-zoom</td></tr>
              <tr><th scope="row">Customization</th><td>None</td><td>Choose language, karaoke or lyric-video mode</td></tr>
            </tbody>
          </table>
        </div>
        <p class="mt-3 small text-secondary">
          A professionally produced karaoke track can still sound cleaner than AI separation on some songs. KaraokAI’s
          advantage is that it works for songs nobody has made a karaoke version of yet.
        </p>
      </section>

      <section class="seo-section" aria-labelledby="create-heading">
        <h2 id="create-heading" class="seo-h2">What Can You Create with KaraokAI?</h2>
        <ul class="check-list">
          <li><strong>Personal karaoke videos</strong> of the film songs, devotionals or indie tracks that never got an official karaoke release.</li>
          <li><strong>Party karaoke</strong> — make a few videos ahead of time and play them straight off a laptop or smart TV.</li>
          <li><strong>Practice tracks</strong> for learning a song: sing over the instrumental with the words in front of you.</li>
          <li><strong>Lyric videos</strong> with the original singer kept in, using “Keep original vocals”.</li>
          <li><strong>Singing cover backing tracks</strong> to record yourself over — check the rights before publishing anything.</li>
        </ul>
        <p>
          If you’re mainly interested in the finished video and how it looks, see the
          <a href="<?= e(base_url('karaoke-video-maker')) ?>">karaoke video maker</a> page; for a broader overview of
          what the AI does at each step, start with our <a href="<?= e(base_url('ai-karaoke-maker')) ?>">AI karaoke maker</a> guide.
        </p>
      </section>

      <section class="seo-section" aria-labelledby="formats-heading">
        <h2 id="formats-heading" class="seo-h2">Supported Formats</h2>
        <div class="glass-card table-responsive">
          <table class="table compare-table">
            <tbody>
              <tr><th scope="row">Input</th><td>YouTube links: youtube.com/watch, youtu.be, YouTube Shorts and music.youtube.com. File uploads aren’t supported.</td></tr>
              <tr><th scope="row">Song length</th><td>Up to <?= $maxDurationMinutes ?> minutes</td></tr>
              <tr><th scope="row">Lyrics languages</th><td>Tamil, Malayalam, Hindi, English, or auto-detect</td></tr>
              <tr><th scope="row">Output</th><td>MP4 video, 1920×1080, H.264 video with AAC audio</td></tr>
              <tr><th scope="row">Modes</th><td>Karaoke (vocals removed) or lyric video (original vocals kept)</td></tr>
            </tbody>
          </table>
        </div>
      </section>

      <section class="seo-section" aria-labelledby="rights-heading">
        <h2 id="rights-heading" class="seo-h2">A Note on Copyright</h2>
        <p>
          Being able to watch a song on YouTube doesn’t mean you’re free to reuse it. KaraokAI makes karaoke videos
          for your personal use; it doesn’t license the music or host the songs you process. Only submit videos you
          own, that are in the public domain, or that you otherwise have the right to use, and check the rights
          before publishing a karaoke video anywhere. The details are in our <a href="<?= e(base_url('terms')) ?>">Terms of Service</a>.
        </p>
      </section>

      <?= partial('partials/seo/faq', ['faqs' => $faqs, 'heading' => 'YouTube to Karaoke FAQ']) ?>
      <?= SeoSchema::script(SeoSchema::howTo('How to turn a YouTube song into a karaoke video', 'Convert a YouTube song into a karaoke video with separated vocals, synchronized lyrics and a video background using KaraokAI.', $page['path'], $steps)) ?>

      <p class="mt-4">
        Want to know what else the same pipeline can do? Read about our
        <a href="<?= e(base_url('karaoke-generator')) ?>">karaoke generator</a> for turning songs into sing-along versions.
      </p>

      <?= partial('partials/seo/cta', [
          'heading' => 'Turn Your YouTube Song Into Karaoke',
          'text' => 'Paste a link, pick the language, and get a ready-to-sing karaoke video.',
          'button' => 'Create Karaoke Video',
      ]) ?>

    </div>
  </div>
</div>

<?= partial('partials/supporting-pages', ['current' => $slug]) ?>
