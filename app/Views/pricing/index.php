<section class="container py-5">
  <div class="text-center mb-5">
    <h1 class="fw-bold mb-2">Simple, pay-as-you-go pricing</h1>
    <p class="text-secondary">1 credit = 1 karaoke video. No subscription, credits don't expire.</p>
  </div>

  <div class="row g-4 justify-content-center">
    <div class="col-md-6 col-lg-3">
      <div class="glass-card p-4 h-100 text-center">
        <h6 class="text-uppercase text-secondary small fw-bold mb-2">Free</h6>
        <div class="display-6 fw-bold mb-1">₹0</div>
        <p class="text-secondary small mb-4">1 karaoke on signup</p>
        <a href="<?= e(base_url('register')) ?>" class="btn btn-outline-secondary w-100">Get Started</a>
      </div>
    </div>
    <?php $popularIndex = (int) floor((count($packages) - 1) / 2); ?>
    <?php foreach ($packages as $index => $package): ?>
      <?php $isPopular = $index === $popularIndex; ?>
      <div class="col-md-6 col-lg-3">
        <div class="glass-card p-4 h-100 text-center<?= $isPopular ? ' border border-2' : '' ?>" <?= $isPopular ? 'style="border-color: var(--ak-saffron, #f2650c) !important;"' : '' ?>>
          <?php if ($isPopular): ?>
            <span class="badge rounded-pill badge-tint mb-2 align-self-center">Most Popular</span>
          <?php endif; ?>
          <h6 class="text-uppercase text-secondary small fw-bold mb-2"><?= e($package['name']) ?></h6>
          <div class="display-6 fw-bold mb-1">₹<?= number_format((float) $package['price_inr'], 0) ?></div>
          <p class="text-secondary small mb-4">
            <?= (int) $package['credits'] ?> karaokes &mdash; ₹<?= number_format((float) $package['price_inr'] / max((int) $package['credits'], 1), 1) ?> each
          </p>
          <button type="button" class="buy-package-btn <?= $isPopular ? 'gradient-btn btn' : 'btn btn-outline-secondary' ?> w-100"
                  data-package-id="<?= (int) $package['id'] ?>" data-package-name="<?= e($package['name']) ?>">Buy Now</button>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="row justify-content-center mt-5">
    <div class="col-lg-8">
      <h5 class="fw-bold mb-3">Frequently asked questions</h5>
      <div class="mb-3">
        <div class="fw-semibold">What counts as one generation?</div>
        <p class="text-secondary small mb-0">One credit is spent per karaoke video you start rendering. If it fails due to a problem on our end, the credit is automatically refunded to your account.</p>
      </div>
      <div class="mb-3">
        <div class="fw-semibold">Do credits expire?</div>
        <p class="text-secondary small mb-0">No — use them whenever you like.</p>
      </div>
      <div class="mb-0">
        <div class="fw-semibold">How do I pay?</div>
        <p class="text-secondary small mb-0">Via Cashfree — UPI, cards, and netbanking are all supported.</p>
      </div>
    </div>
  </div>
</section>

<div class="modal fade" id="buyPackageModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Confirm your purchase</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="mb-3">Buying <strong id="buyPackageModalName"></strong>.</p>
        <label class="form-label" for="buyPackagePhone">Phone number</label>
        <input type="tel" class="form-control form-control-ak" id="buyPackagePhone" maxlength="10"
               value="<?= e($userPhone ?? '') ?>" placeholder="10-digit mobile number">
        <div class="form-text">Required by Cashfree to process the payment.</div>
        <div id="buyPackageError" class="text-danger small mt-2" style="display:none;"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="gradient-btn btn" id="buyPackageConfirmBtn">Continue to Payment</button>
      </div>
    </div>
  </div>
</div>

<script src="https://sdk.cashfree.com/js/v3/cashfree.js"></script>
