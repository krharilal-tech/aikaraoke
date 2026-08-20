<section class="container py-5">
  <div class="row justify-content-center">
    <div class="col-lg-6">
      <div class="glass-card p-5 text-center">
        <?php if ($outcome === 'success'): ?>
          <i class="bi bi-check-circle-fill text-success display-4 mb-3"></i>
          <h1 class="h4 fw-bold mb-2">Payment successful</h1>
          <p class="text-secondary mb-4"><?= (int) $credits ?> credits have been added to your account.</p>
          <a href="<?= e(base_url('/')) ?>" class="gradient-btn btn">Start a karaoke video</a>
        <?php elseif ($outcome === 'failed'): ?>
          <i class="bi bi-x-circle-fill text-danger display-4 mb-3"></i>
          <h1 class="h4 fw-bold mb-2">Payment failed</h1>
          <p class="text-secondary mb-4">Your payment could not be completed, and no credits were charged.</p>
          <a href="<?= e(base_url('pricing')) ?>" class="btn btn-outline-secondary">Try again</a>
        <?php elseif ($outcome === 'pending'): ?>
          <i class="bi bi-hourglass-split text-secondary display-4 mb-3"></i>
          <h1 class="h4 fw-bold mb-2">Payment processing</h1>
          <p class="text-secondary mb-4">We're still confirming your payment with Cashfree — this can take a minute. Refresh this page shortly, or check <a href="<?= e(base_url('jobs')) ?>">My Videos</a> later, your credits will appear once it clears.</p>
          <a href="<?= e(base_url('pricing')) ?>" class="btn btn-outline-secondary">Back to Pricing</a>
        <?php else: ?>
          <i class="bi bi-question-circle-fill text-secondary display-4 mb-3"></i>
          <h1 class="h4 fw-bold mb-2">We couldn't find that order</h1>
          <p class="text-secondary mb-4">If you completed a payment, check <a href="<?= e(base_url('jobs')) ?>">My Videos</a> — credits may still be on their way.</p>
          <a href="<?= e(base_url('pricing')) ?>" class="btn btn-outline-secondary">Back to Pricing</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>
