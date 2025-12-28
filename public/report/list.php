<?php
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/helpers.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/db.php';
require_login();
$pdo = db();
$user = current_user();

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20; $offset = ($page-1)*$perPage;

if (in_array($user['role'], ['super_admin','reviewer','viewer'])) {
  $total = (int)$pdo->query('SELECT COUNT(*) FROM reports')->fetchColumn();
  $st = $pdo->query('SELECT r.*, u.name as unit_name, us.name as user_name FROM reports r JOIN units u ON u.id=r.unit_id JOIN users us ON us.id=r.user_id ORDER BY r.id DESC LIMIT '.(int)$perPage.' OFFSET '.(int)$offset);
} else {
  $ct = $pdo->prepare('SELECT COUNT(*) FROM reports WHERE user_id=?'); $ct->execute([$user['id']]); $total = (int)$ct->fetchColumn();
  $st = $pdo->prepare('SELECT r.*, u.name as unit_name FROM reports r JOIN units u ON u.id=r.unit_id WHERE r.user_id = ? ORDER BY r.id DESC LIMIT '.(int)$perPage.' OFFSET '.(int)$offset);
  $st->execute([$user['id']]);
}
$rows = $st->fetchAll();
$totalPages = max(1, (int)ceil($total / $perPage));

include __DIR__ . '/../../templates/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4>रिपोर्ट सूची / Reports</h4>
  <div class="d-flex gap-2">
    <a href="<?= app_base_url('report/export.php') ?>" class="btn btn-outline-success">Export CSV</a>
    <?php if (in_array($user['role'], ['officer','super_admin'])): ?>
    <a href="<?= app_base_url('report/new.php') ?>" class="btn btn-primary">नई रिपोर्ट / New Report</a>
    <?php endif; ?>
  </div>
</div>

<div class="table-responsive">
  <table class="table table-striped align-middle">
    <thead>
      <tr>
        <th>ID</th>
        <th>Unit</th>
        <th>Period</th>
        <th>Status</th>
        <th>By</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
      <tr>
        <td>#<?= (int)$r['id'] ?></td>
        <td><?= esc($r['unit_name'] ?? '') ?></td>
        <td><?= esc(($r['period_quarter']?('Q'.$r['period_quarter']):'') . ' ' . ($r['period_year'] ?? '')) ?></td>
        <td><span class="badge bg-secondary"><?= esc($r['status']) ?></span></td>
        <td><?= esc($r['user_name'] ?? '') ?></td>
        <td>
          <a class="btn btn-sm btn-outline-primary" href="<?= app_base_url('report/view.php?id='.(int)$r['id']) ?>">View</a>
          <a class="btn btn-sm btn-outline-success" href="<?= app_base_url('report/export.php?id='.(int)$r['id']) ?>">CSV</a>
          <?php if (in_array($r['status'], ['draft','rejected']) && (int)$r['user_id'] === (int)$user['id']): ?>
          <a class="btn btn-sm btn-outline-secondary" href="<?= app_base_url('report/edit.php?id='.(int)$r['id']) ?>">Edit</a>
          <form method="post" action="<?= app_base_url('report/delete.php') ?>" class="d-inline" onsubmit="return confirm('Delete this report? This cannot be undone.');">
            <?= csrf_input() ?>
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button class="btn btn-sm btn-outline-danger">Delete</button>
          </form>
          <?php endif; ?>
          <?php if ($user['role']==='super_admin' && $r['status']!=='approved'): ?>
          <form method="post" action="<?= app_base_url('report/delete.php') ?>" class="d-inline" onsubmit="return confirm('Admin delete this report? This cannot be undone.');">
            <?= csrf_input() ?>
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button class="btn btn-sm btn-danger">Admin Delete</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php if ($totalPages>1): ?>
<nav aria-label="Reports pages">
  <ul class="pagination">
    <?php for($p=1;$p<=$totalPages;$p++): ?>
      <li class="page-item <?= $p===$page?'active':'' ?>"><a class="page-link" href="?page=<?= $p ?>"><?= $p ?></a></li>
    <?php endfor; ?>
  </ul>
</nav>
<?php endif; ?>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
