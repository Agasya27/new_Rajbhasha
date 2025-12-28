<?php
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/helpers.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_role(['super_admin']);
$pdo = db();

if (is_post()) {
  verify_csrf();
  $action = $_POST['action'] ?? '';
  if ($action === 'create') {
    $pdo->prepare('INSERT INTO units(name, code) VALUES(?,?)')->execute([
      trim($_POST['name'] ?? ''), trim($_POST['code'] ?? '')
    ]);
    flash_set('success', 'Unit created');
    redirect('units.php');
  } elseif ($action === 'delete') {
    $pdo->prepare('DELETE FROM units WHERE id=?')->execute([(int)$_POST['id']]);
    flash_set('success', 'Unit deleted');
    redirect('units.php');
  }
}

$units = $pdo->query('SELECT * FROM units ORDER BY name')->fetchAll();
include __DIR__ . '/../../templates/header.php';
?>
<h4>इकाइयों का प्रबंधन / Unit Management</h4>
<div class="card mb-3"><div class="card-body">
  <form method="post" class="row g-2">
    <?= csrf_input() ?>
    <input type="hidden" name="action" value="create">
    <div class="col-md-6"><input class="form-control" name="name" placeholder="Unit Name" required></div>
    <div class="col-md-3"><input class="form-control" name="code" placeholder="Code" required></div>
    <div class="col-md-3"><button class="btn btn-primary w-100">Add Unit</button></div>
  </form>
</div></div>

<div class="table-responsive">
  <table class="table table-striped align-middle">
    <thead><tr><th>ID</th><th>Name</th><th>Code</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($units as $u): ?>
      <tr>
        <td><?= (int)$u['id'] ?></td>
        <td><?= esc($u['name']) ?></td>
        <td><?= esc($u['code']) ?></td>
        <td>
          <form method="post" class="d-inline" onsubmit="return confirm('Delete unit?')">
            <?= csrf_input() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
            <button class="btn btn-sm btn-outline-danger">Delete</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
