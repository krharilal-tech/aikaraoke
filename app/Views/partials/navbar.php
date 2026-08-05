<nav class="navbar navbar-expand-lg app-navbar sticky-top">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center gap-2" href="<?= e(base_url('/')) ?>">
      <span class="brand-icon"><i class="bi bi-mic-fill"></i></span>
      <span class="brand-text">AI Karaoke Maker</span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
        <li class="nav-item"><a class="nav-link" href="<?= e(base_url('/')) ?>"><i class="bi bi-house-door me-1"></i>Home</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= e(base_url('/pricing')) ?>"><i class="bi bi-tags me-1"></i>Pricing</a></li>
        <?php if (\App\Core\Auth::check()): ?>
          <li class="nav-item"><a class="nav-link" href="<?= e(base_url('/jobs')) ?>"><i class="bi bi-clock-history me-1"></i>My Videos</a></li>
          <?php if (\App\Core\Auth::isAdmin()): ?>
            <li class="nav-item"><a class="nav-link" href="<?= e(base_url('/settings')) ?>"><i class="bi bi-gear me-1"></i>Settings</a></li>
            <li class="nav-item"><a class="nav-link" href="<?= e(base_url('/admin/users')) ?>"><i class="bi bi-shield-lock me-1"></i>Admin</a></li>
          <?php endif; ?>
          <li class="nav-item">
            <span class="badge rounded-pill badge-tint px-3 py-2" title="Karaoke credits remaining">
              <i class="bi bi-coin me-1"></i><?= (int) \App\Models\Credit::balance((int) \App\Core\Auth::id()) ?> credits
            </span>
          </li>
          <li class="nav-item">
            <form method="post" action="<?= e(base_url('logout')) ?>" class="d-inline">
              <?= csrf_field() ?>
              <button type="submit" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-box-arrow-right me-1"></i>Sign Out
              </button>
            </form>
          </li>
        <?php else: ?>
          <li class="nav-item"><a class="nav-link" href="<?= e(base_url('/login')) ?>">Sign In</a></li>
          <li class="nav-item">
            <a class="btn btn-sm gradient-btn" href="<?= e(base_url('/register')) ?>">Sign Up Free</a>
          </li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>
