<?php
// Each status as the admin should read it: [label, badge classes, explanation].
$statusInfo = [
    'started' => ['Clicked Buy Now', 'bg-secondary-subtle text-secondary-emphasis', "Opened the purchase box but didn't continue to payment."],
    'created' => ['Not finished', 'bg-warning-subtle text-warning-emphasis', "Went to Cashfree's payment page but hasn't paid (yet)."],
    'paid' => ['Paid', 'bg-success-subtle text-success-emphasis', 'Payment received, credits added.'],
    'failed' => ['Failed', 'bg-danger-subtle text-danger-emphasis', "The payment failed, was cancelled, or Cashfree couldn't create the order."],
];
?>
<section class="container py-5">
  <div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 fw-bold mb-0"><i class="bi bi-receipt me-2 text-gradient"></i>Orders</h1>
    <div>
      <a href="<?= e(base_url('admin/users')) ?>" class="btn btn-sm btn-outline-secondary">Users</a>
      <a href="<?= e(base_url('admin/orders')) ?>" class="btn btn-sm btn-outline-secondary">Orders</a>
      <a href="<?= e(base_url('admin/packages')) ?>" class="btn btn-sm btn-outline-secondary">Packages</a>
    </div>
  </div>

  <div class="d-flex flex-wrap gap-2 mb-4">
    <?php foreach ($statusInfo as $status => [$label, $badge, $help]): ?>
      <span class="badge rounded-pill <?= e($badge) ?> px-3 py-2" title="<?= e($help) ?>">
        <?= e($label) ?>: <?= (int) ($counts[$status] ?? 0) ?>
      </span>
    <?php endforeach; ?>
    <span class="badge rounded-pill text-bg-dark px-3 py-2">Revenue: ₹<?= number_format($revenue, 2) ?></span>
  </div>

  <div class="glass-card p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>When</th>
            <th>User</th>
            <th>Phone</th>
            <th>Package</th>
            <th>Amount</th>
            <th>Status</th>
            <th>Order ID</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($orders === []): ?>
            <tr><td colspan="7" class="text-center text-secondary py-4">No one has clicked Buy Now yet.</td></tr>
          <?php endif; ?>
          <?php foreach ($orders as $order): ?>
            <?php [$label, $badge, $help] = $statusInfo[$order['status']] ?? [$order['status'], 'text-bg-light', '']; ?>
            <tr>
              <td class="text-secondary small text-nowrap"><?= e($order['created_at']) ?></td>
              <td>
                <div><?= e($order['user_name'] ?? '—') ?></div>
                <?php if (!empty($order['user_email'])): ?>
                  <a class="small" href="mailto:<?= e($order['user_email']) ?>"><?= e($order['user_email']) ?></a>
                <?php endif; ?>
              </td>
              <td class="text-nowrap">
                <?php if (!empty($order['user_phone'])): ?>
                  <a href="tel:<?= e($order['user_phone']) ?>"><?= e($order['user_phone']) ?></a>
                <?php else: ?>
                  <span class="text-secondary">—</span>
                <?php endif; ?>
              </td>
              <td><?= e($order['package_name'] ?? ('#' . $order['package_id'])) ?> <span class="text-secondary small">(<?= (int) $order['credits'] ?> credits)</span></td>
              <td class="text-nowrap">₹<?= number_format((float) $order['amount_inr'], 2) ?></td>
              <td><span class="badge <?= e($badge) ?>" title="<?= e($help) ?>"><?= e($label) ?></span></td>
              <td class="small text-secondary">
                <code><?= e($order['cf_order_id']) ?></code>
                <?php if (!empty($order['cf_payment_id'])): ?>
                  <div>Payment: <code><?= e($order['cf_payment_id']) ?></code></div>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <p class="text-secondary small mt-3 mb-0">
    Phone numbers are the user's latest, entered at checkout. A user who only clicked Buy Now may not have one yet.
    Times are the server's time.
  </p>
</section>
