<?php
/**
 * "Explore KaraokAI" — links to every SEO landing page, rendered as the
 * last section of the homepage and each landing page (i.e. directly above
 * the footer). $current is the slug of the page being viewed, if any: that
 * card is highlighted and not linked to itself.
 *
 * @var string|null $current
 */
$current = $current ?? null;
?>
<section class="supporting-pages py-5" aria-labelledby="explore-heading">
  <div class="container">
    <div class="text-center mb-4">
      <h2 id="explore-heading" class="fw-bold mb-2">Explore <?= e(config('seo_pages.brand')) ?></h2>
      <p class="text-secondary mb-0">Explore the different ways <?= e(config('seo_pages.brand')) ?> can help you create karaoke songs and videos with AI.</p>
    </div>
    <div class="row g-3 row-cols-1 row-cols-sm-2 row-cols-lg-5">
      <?php foreach (config('seo_pages.pages') as $slug => $item): ?>
        <div class="col">
          <?php if ($slug === $current): ?>
            <div class="glass-card explore-card is-current h-100 p-3" aria-current="page">
              <span class="step-icon mb-3"><i class="bi <?= e($item['icon']) ?>"></i></span>
              <h3 class="h6 fw-bold mb-1"><?= e($item['name']) ?></h3>
              <p class="text-secondary small mb-2"><?= e($item['blurb']) ?></p>
              <span class="badge rounded-pill badge-tint">You're here</span>
            </div>
          <?php else: ?>
            <a href="<?= e(base_url($item['path'])) ?>" class="glass-card explore-card h-100 p-3 d-block text-decoration-none">
              <span class="step-icon mb-3"><i class="bi <?= e($item['icon']) ?>"></i></span>
              <h3 class="h6 fw-bold mb-1"><?= e($item['name']) ?></h3>
              <p class="text-secondary small mb-0"><?= e($item['blurb']) ?></p>
            </a>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
