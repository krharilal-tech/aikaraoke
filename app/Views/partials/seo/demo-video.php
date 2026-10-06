<?php
/**
 * Demo video slot for /youtube-to-karaoke. Drop the finished demo at
 * public/assets/video/youtube-to-karaoke-demo.mp4 (and optionally a poster
 * frame at public/assets/img/screens/demo-poster.webp) and it's embedded
 * automatically. Until then this renders the pipeline as a static step
 * strip instead of an empty player.
 */
$videoPath = 'video/youtube-to-karaoke-demo.mp4';
$posterPath = 'img/screens/demo-poster.webp';
$assetsRoot = config('paths.root') . '/public/assets/';
$steps = [
    ['bi-youtube', 'YouTube link'],
    ['bi-soundwave', 'Vocals removed'],
    ['bi-card-text', 'Lyrics synced'],
    ['bi-image', 'Background added'],
    ['bi-play-btn', 'Karaoke video'],
];
?>
<div class="media-frame demo-video">
  <?php if (is_file($assetsRoot . $videoPath)): ?>
    <video class="karaoke-preview" controls playsinline preload="none"
           <?= is_file($assetsRoot . $posterPath) ? 'poster="' . e(asset($posterPath)) . '"' : '' ?>
           aria-label="Demo: turning a YouTube song into a karaoke video with KaraokAI">
      <source src="<?= e(asset($videoPath)) ?>" type="video/mp4">
    </video>
  <?php else: ?>
    <div class="demo-standin p-4" data-demo-video-slot="<?= e($videoPath) ?>">
      <ol class="flow-strip list-unstyled mb-0">
        <?php foreach ($steps as [$icon, $label]): ?>
          <li><span class="step-icon"><i class="bi <?= e($icon) ?>"></i></span><span class="small fw-semibold"><?= e($label) ?></span></li>
        <?php endforeach; ?>
      </ol>
    </div>
  <?php endif; ?>
</div>
