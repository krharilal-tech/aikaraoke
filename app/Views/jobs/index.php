<section class="container py-5">
  <h1 class="h3 fw-bold mb-4"><i class="bi bi-clock-history me-2 text-gradient"></i>My Videos</h1>

  <?php if ($jobs === []): ?>
    <div class="glass-card p-5 text-center">
      <i class="bi bi-music-note-beamed text-secondary" style="font-size:2.5rem;"></i>
      <p class="text-secondary mt-3 mb-3">You haven't generated any karaoke videos yet.</p>
      <a href="<?= e(base_url('/')) ?>" class="gradient-btn btn"><i class="bi bi-magic me-2"></i>Generate Your First Video</a>
    </div>
  <?php else: ?>
    <div class="row g-3">
      <?php foreach ($jobs as $job): ?>
        <div class="col-md-6 col-lg-4">
          <a href="<?= e(base_url('jobs/' . $job['id'])) ?>" class="text-decoration-none">
            <div class="glass-card p-3 h-100">
              <div class="d-flex align-items-center gap-3">
                <?php if (!empty($job['thumbnail_url'])): ?>
                  <img src="<?= e($job['thumbnail_url']) ?>" class="rounded-3" style="width:88px;height:50px;object-fit:cover;" alt="">
                <?php else: ?>
                  <div class="rounded-3 tint-box d-flex align-items-center justify-content-center" style="width:88px;height:50px;">
                    <i class="bi bi-music-note" style="color: var(--ak-saffron-strong);"></i>
                  </div>
                <?php endif; ?>
                <div class="flex-grow-1 overflow-hidden">
                  <div class="fw-semibold text-truncate"><?= e($job['title'] ?? 'Untitled job #' . $job['id']) ?></div>
                  <div class="small text-secondary text-capitalize"><?= e(str_replace('_', ' ', $job['state'])) ?></div>
                </div>
              </div>
            </div>
          </a>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
