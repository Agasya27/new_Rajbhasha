<?php
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/helpers.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_role(['super_admin']);
$pdo = db();

// Create user
if (is_post() && ($_POST['action'] ?? '') === 'create') {
  verify_csrf();
  // Basic server-side validation
  $name  = trim($_POST['name'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $pass  = (string)($_POST['password'] ?? '');
  $role  = (string)($_POST['role'] ?? 'viewer');
  $unit  = (int)($_POST['unit_id'] ?? 1);

  if ($name === '' || $email === '' || $pass === '') {
    flash_set('danger','Name, email and password are required.');
    redirect('users.php');
  }
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    flash_set('danger','Please enter a valid email address.');
    redirect('users.php');
  }
  $allowedRoles = ['officer','reviewer','viewer','super_admin'];
  if (!in_array($role, $allowedRoles, true)) { $role = 'viewer'; }

  // Prevent duplicate email (unique index may throw otherwise)
  $check = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
  $check->execute([$email]);
  if ($check->fetch()) {
    flash_set('danger','Email already exists. Please use a different email.');
    redirect('users.php');
  }

  try {
    $st = $pdo->prepare('INSERT INTO users(name,email,password_hash,role,unit_id) VALUES(?,?,?,?,?)');
    $st->execute([
      $name,
      $email,
      password_hash($pass, PASSWORD_DEFAULT),
      $role,
      $unit
    ]);
    flash_set('success','User created');
  } catch (PDOException $e) {
    // Handle race condition or DB constraint errors gracefully
    if ((int)$e->getCode() === 23000) {
      flash_set('danger','Email already exists.');
    } else {
      flash_set('danger','Could not create user.');
    }
  }
  redirect('admin/users.php');
}

// Delete user
if (isset($_GET['del'])) {
  $id = (int)$_GET['del'];
  $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
  flash_set('success','User deleted');
  redirect('admin/users.php');
}

$units = $pdo->query('SELECT * FROM units ORDER BY name')->fetchAll();
$users = $pdo->query('SELECT u.*, un.name unit_name FROM users u LEFT JOIN units un ON un.id=u.unit_id ORDER BY u.id DESC')->fetchAll();

include __DIR__ . '/../../templates/header.php';
?>
<h4>उपयोगकर्ता प्रबंधन / User Management</h4>

<div class="card mb-3"><div class="card-body">
  <form method="post" class="row g-2">
    <?= csrf_input() ?>
    <input type="hidden" name="action" value="create">
    <div class="col-md-3"><input class="form-control" name="name" placeholder="नाम / Name" required></div>
    <div class="col-md-3"><input class="form-control" type="email" name="email" placeholder="Email" required></div>
    <div class="col-md-2"><input class="form-control" type="password" name="password" placeholder="Password" required></div>
    <div class="col-md-2">
      <select class="form-select" name="role">
        <option value="officer">officer</option>
        <option value="reviewer">reviewer</option>
        <option value="viewer">viewer</option>
        <option value="super_admin">super_admin</option>
      </select>
    </div>
    <div class="col-md-2">
      <select class="form-select" name="unit_id">
        <?php foreach ($units as $un): ?><option value="<?= (int)$un['id'] ?>"><?= esc($un['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="col-12"><button class="btn btn-primary">Add User</button></div>
  </form>
</div></div>

<div class="table-responsive">
  <table class="table table-striped">
    <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Unit</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($users as $u): ?>
      <tr>
        <td><?= (int)$u['id'] ?></td>
        <td><?= esc($u['name']) ?></td>
        <td><?= esc($u['email']) ?></td>
        <td><span class="badge bg-info"><?= esc($u['role']) ?></span></td>
        <td><?= esc($u['unit_name'] ?? '') ?></td>
        <td>
          <?php if ($u['role'] !== 'super_admin'): ?>
          <a href="?del=<?= (int)$u['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete user?')">Delete</a>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
