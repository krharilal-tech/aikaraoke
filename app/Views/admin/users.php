<section class="container py-5">
  <div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 fw-bold mb-0"><i class="bi bi-people me-2 text-gradient"></i>Users</h1>
    <div>
      <a href="<?= e(base_url('admin/users')) ?>" class="btn btn-sm btn-outline-secondary">Users</a>
      <a href="<?= e(base_url('admin/packages')) ?>" class="btn btn-sm btn-outline-secondary">Packages</a>
    </div>
  </div>

  <?php if ($flash): ?>
    <div class="alert alert-<?= e($flash['type'] === 'success' ? 'success' : 'danger') ?> mb-4"><?= e($flash['message']) ?></div>
  <?php endif; ?>

  <div class="glass-card p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>Name</th>
            <th>Email</th>
            <th>Role</th>
            <th>Signed up</th>
            <th>Credits</th>
            <th>Adjust</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $user): ?>
            <tr>
              <td><?= e($user['name'] ?? '—') ?></td>
              <td><?= e($user['email']) ?></td>
              <td><span class="badge text-bg-<?= $user['role'] === 'admin' ? 'dark' : 'secondary' ?>"><?= e($user['role']) ?></span></td>
              <td class="text-secondary small"><?= e($user['created_at']) ?></td>
              <td class="fw-semibold"><?= (int) $user['balance'] ?></td>
              <td style="min-width:220px;">
                <form method="post" action="<?= e(base_url('admin/users/' . $user['id'] . '/credits')) ?>" class="d-flex gap-2">
                  <?= csrf_field() ?>
                  <input type="number" name="delta" class="form-control form-control-sm" style="width:90px;" placeholder="&plusmn;5" required>
                  <input type="text" name="reason" class="form-control form-control-sm" placeholder="Reason (optional)">
                  <button type="submit" class="btn btn-sm btn-outline-secondary">Apply</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>
