<section class="container py-5">
  <div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 fw-bold mb-0"><i class="bi bi-box-seam me-2 text-gradient"></i>Packages</h1>
    <div>
      <a href="<?= e(base_url('admin/users')) ?>" class="btn btn-sm btn-outline-secondary">Users</a>
      <a href="<?= e(base_url('admin/packages')) ?>" class="btn btn-sm btn-outline-secondary">Packages</a>
    </div>
  </div>

  <?php if ($flash): ?>
    <div class="alert alert-<?= e($flash['type'] === 'success' ? 'success' : 'danger') ?> mb-4"><?= e($flash['message']) ?></div>
  <?php endif; ?>

  <!--
    Each row needs two independent submit actions (save, toggle) with
    inputs spread across several <td>s. A <form> can't wrap <td>s or be a
    child of <tr> per the HTML spec, so instead: an empty, out-of-band
    <form> per action below, and every input/button references it by id
    via the form="..." attribute instead of being nested inside it.
  -->
  <?php foreach ($packages as $package): ?>
    <form id="pkg-save-<?= (int) $package['id'] ?>" method="post" action="<?= e(base_url('admin/packages/' . $package['id'])) ?>"></form>
    <form id="pkg-toggle-<?= (int) $package['id'] ?>" method="post" action="<?= e(base_url('admin/packages/' . $package['id'] . '/toggle')) ?>"></form>
  <?php endforeach; ?>

  <div class="glass-card p-0 mb-4">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>Name</th>
            <th>Credits</th>
            <th>Price (₹)</th>
            <th>Sort order</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($packages as $package): ?>
            <?php $saveForm = 'pkg-save-' . (int) $package['id']; $toggleForm = 'pkg-toggle-' . (int) $package['id']; ?>
            <tr>
              <td style="min-width:160px;">
                <input type="hidden" form="<?= e($saveForm) ?>" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="text" form="<?= e($saveForm) ?>" name="name" class="form-control form-control-sm" value="<?= e($package['name']) ?>" required>
              </td>
              <td style="min-width:100px;">
                <input type="number" form="<?= e($saveForm) ?>" name="credits" class="form-control form-control-sm" value="<?= (int) $package['credits'] ?>" min="1" required>
              </td>
              <td style="min-width:120px;">
                <input type="number" form="<?= e($saveForm) ?>" name="price_inr" class="form-control form-control-sm" value="<?= e($package['price_inr']) ?>" step="0.01" min="0.01" required>
              </td>
              <td style="min-width:100px;">
                <input type="number" form="<?= e($saveForm) ?>" name="sort_order" class="form-control form-control-sm" value="<?= (int) $package['sort_order'] ?>">
              </td>
              <td>
                <span class="badge text-bg-<?= (int) $package['is_active'] === 1 ? 'success' : 'secondary' ?>">
                  <?= (int) $package['is_active'] === 1 ? 'Active' : 'Inactive' ?>
                </span>
              </td>
              <td class="text-end text-nowrap">
                <button type="submit" form="<?= e($saveForm) ?>" class="btn btn-sm btn-outline-secondary">Save</button>
                <input type="hidden" form="<?= e($toggleForm) ?>" name="_csrf" value="<?= e(csrf_token()) ?>">
                <button type="submit" form="<?= e($toggleForm) ?>" class="btn btn-sm btn-outline-secondary">
                  <?= (int) $package['is_active'] === 1 ? 'Deactivate' : 'Activate' ?>
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="glass-card p-4">
    <h6 class="fw-bold mb-3">Add a package</h6>
    <form method="post" action="<?= e(base_url('admin/packages')) ?>" class="row g-2 align-items-end">
      <?= csrf_field() ?>
      <div class="col-md-3">
        <label class="form-label small">Name</label>
        <input type="text" name="name" class="form-control form-control-sm" required>
      </div>
      <div class="col-md-2">
        <label class="form-label small">Credits</label>
        <input type="number" name="credits" class="form-control form-control-sm" min="1" required>
      </div>
      <div class="col-md-2">
        <label class="form-label small">Price (₹)</label>
        <input type="number" name="price_inr" class="form-control form-control-sm" step="0.01" min="0.01" required>
      </div>
      <div class="col-md-2">
        <label class="form-label small">Sort order</label>
        <input type="number" name="sort_order" class="form-control form-control-sm" value="0">
      </div>
      <div class="col-md-3">
        <button type="submit" class="gradient-btn btn btn-sm w-100"><i class="bi bi-plus-lg me-1"></i>Add Package</button>
      </div>
    </form>
  </div>
</section>
