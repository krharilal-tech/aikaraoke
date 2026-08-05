<footer class="app-footer text-center py-4">
  <div class="container">
    <nav class="mb-2">
      <a href="<?= e(base_url('privacy')) ?>" class="text-secondary small me-3 text-decoration-none">Privacy Policy</a>
      <a href="<?= e(base_url('terms')) ?>" class="text-secondary small me-3 text-decoration-none">Terms of Service</a>
      <a href="<?= e(base_url('refund-policy')) ?>" class="text-secondary small me-3 text-decoration-none">Refund &amp; Cancellation</a>
      <a href="<?= e(base_url('contact')) ?>" class="text-secondary small text-decoration-none">Contact Us</a>
    </nav>
    <small class="text-secondary">
      &copy; <?= date('Y') ?> <?= e(config('app.name')) ?> &mdash; AI-powered karaoke video generation.
    </small>
  </div>
</footer>
