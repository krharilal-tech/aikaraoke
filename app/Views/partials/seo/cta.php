<?php
/**
 * Conversion block pointing at the real generator form on the homepage.
 *
 * @var string $heading
 * @var string $text
 * @var string|null $button
 */
?>
<section class="glass-card seo-cta text-center p-4 p-md-5 my-5">
  <h2 class="seo-h2 mb-2"><?= e($heading) ?></h2>
  <p class="text-secondary mx-auto mb-4" style="max-width: 560px;"><?= e($text) ?></p>
  <a href="<?= e(base_url('/')) ?>#generateCard" class="gradient-btn btn btn-lg">
    <i class="bi bi-magic me-2"></i><?= e($button ?? 'Create Karaoke Video') ?>
  </a>
  <p class="text-secondary small mt-3 mb-0">
    New accounts get 1 free karaoke video. <a href="<?= e(base_url('pricing')) ?>">See pricing</a>
  </p>
</section>
