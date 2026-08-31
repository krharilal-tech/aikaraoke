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
            <th>Status</th>
            <th>Signed up</th>
            <th>Credits</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $user): ?>
            <?php $isBlocked = ($user['status'] ?? 'active') === 'blocked'; ?>
            <tr class="<?= $isBlocked ? 'opacity-75' : '' ?>">
              <td><?= e($user['name'] ?? '—') ?></td>
              <td><?= e($user['email']) ?></td>
              <td><span class="badge text-bg-<?= $user['role'] === 'admin' ? 'dark' : 'secondary' ?>"><?= e($user['role']) ?></span></td>
              <td>
                <?php if ($isBlocked): ?>
                  <span class="badge text-bg-danger" title="<?= e($user['blocked_reason'] ?? '') ?>">blocked</span>
                <?php else: ?>
                  <span class="badge bg-success-subtle text-success-emphasis">active</span>
                <?php endif; ?>
              </td>
              <td class="text-secondary small"><?= e($user['created_at']) ?></td>
              <td class="fw-semibold"><?= (int) $user['balance'] ?></td>
              <td style="min-width:260px;">
                <form method="post" action="<?= e(base_url('admin/users/' . $user['id'] . '/credits')) ?>" class="d-flex gap-2 mb-2">
                  <?= csrf_field() ?>
                  <input type="number" name="delta" class="form-control form-control-sm" style="width:90px;" placeholder="&plusmn;5" required>
                  <input type="text" name="reason" class="form-control form-control-sm" placeholder="Reason (optional)">
                  <button type="submit" class="btn btn-sm btn-outline-secondary">Apply</button>
                </form>
                <?php if ($user['role'] !== 'admin'): ?>
                  <form method="post" action="<?= e(base_url('admin/users/' . $user['id'] . '/block')) ?>">
                    <?= csrf_field() ?>
                    <?php if (!$isBlocked): ?>
                      <input type="text" name="reason" class="form-control form-control-sm mb-1" placeholder="Reason (optional)">
                    <?php endif; ?>
                    <button type="submit" class="btn btn-sm <?= $isBlocked ? 'btn-outline-success' : 'btn-outline-danger' ?>">
                      <i class="bi bi-<?= $isBlocked ? 'unlock' : 'slash-circle' ?> me-1"></i><?= $isBlocked ? 'Unblock' : 'Block' ?>
                    </button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>
