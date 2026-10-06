<?php
/**
 * Slot for a real product screenshot. Drop the image at
 * public/assets/img/screens/{$file} (WebP, ~1280px wide) and it replaces the
 * illustrated stand-in automatically — no template change needed. The
 * stand-in is deliberately a plain icon panel, not a fake screenshot.
 *
 * @var string $file e.g. "youtube-url-input.webp"
 * @var string $alt
 * @var string $caption
 * @var string $icon Bootstrap Icons class for the stand-in
 */
$relativePath = 'img/screens/' . $file;
$absolutePath = config('paths.root') . '/public/assets/' . $relativePath;
?>
<figure class="media-frame mb-0">
  <?php if (is_file($absolutePath)): ?>
    <?php [$width, $height] = getimagesize($absolutePath) ?: [1280, 720]; ?>
    <img src="<?= e(asset($relativePath)) ?>" alt="<?= e($alt) ?>" width="<?= (int) $width ?>" height="<?= (int) $height ?>"
         loading="lazy" decoding="async" class="img-fluid">
  <?php else: ?>
    <div class="media-standin" role="img" aria-label="<?= e($alt) ?>" data-screenshot-slot="<?= e($file) ?>">
      <i class="bi <?= e($icon) ?>"></i>
    </div>
  <?php endif; ?>
  <figcaption class="small text-secondary p-3"><?= e($caption) ?></figcaption>
</figure>
