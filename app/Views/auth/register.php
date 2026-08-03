<section class="container py-5">
  <div class="row justify-content-center">
    <div class="col-md-6 col-lg-4">
      <div class="text-center mb-4">
        <span class="brand-icon d-inline-flex mb-2"><i class="bi bi-mic-fill"></i></span>
        <h1 class="h4 fw-bold"><?= e(config('app.name')) ?></h1>
        <p class="text-secondary small mb-0">Create your account &mdash; your first karaoke is free.</p>
      </div>
      <div class="glass-card p-4 p-md-5">
        <?php if ($error): ?>
          <div class="alert alert-danger small"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if ($googleEnabled): ?>
          <a href="<?= e(base_url('auth/google') . ($next !== '' ? '?next=' . urlencode($next) : '')) ?>" class="btn btn-outline-secondary btn-lg w-100 mb-3 d-flex align-items-center justify-content-center gap-2">
            <i class="bi bi-google"></i> Continue with Google
          </a>
          <div class="d-flex align-items-center gap-3 my-4">
            <hr class="flex-grow-1">
            <span class="text-secondary small">or</span>
            <hr class="flex-grow-1">
          </div>
        <?php endif; ?>

        <form method="post" action="<?= e(base_url('register')) ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="next" value="<?= e($next) ?>">
          <div class="mb-3">
            <label class="form-label" for="name">Name</label>
            <input type="text" class="form-control form-control-ak" id="name" name="name" required autofocus>
          </div>
          <div class="mb-3">
            <label class="form-label" for="email">Email</label>
            <input type="email" class="form-control form-control-ak" id="email" name="email" required>
          </div>
          <div class="mb-4">
            <label class="form-label" for="password">Password</label>
            <input type="password" class="form-control form-control-ak" id="password" name="password" minlength="8" required>
            <div class="form-text">At least 8 characters.</div>
          </div>
          <button type="submit" class="gradient-btn btn btn-lg w-100"><i class="bi bi-person-plus me-2"></i>Create Account</button>
        </form>

        <p class="text-secondary small text-center mt-4 mb-0">
          Already have an account?
          <a href="<?= e(base_url('login') . ($next !== '' ? '?next=' . urlencode($next) : '')) ?>">Sign in</a>
        </p>
      </div>
    </div>
  </div>
</section>
