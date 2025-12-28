<?php
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/helpers.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/csrf.php';

require_login();
verify_csrf();

if (!is_post()) {
  http_response_code(405);
  echo 'Method Not Allowed';
  exit;
}

$pdo = db();
$user = current_user();
$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
  flash_set('danger', 'Invalid report id.');
  redirect('report/list.php');
}

// Load report
$st = $pdo->prepare('SELECT id, user_id, status FROM reports WHERE id = ? LIMIT 1');
$st->execute([$id]);
$report = $st->fetch();

if (!$report) {
  flash_set('warning', 'Report not found or already deleted.');
  redirect('report/list.php');
}

$canDelete = false;
if ($user['role'] === 'super_admin') {
  // Allow super admin to delete any non-approved report
  $canDelete = ($report['status'] !== 'approved');
} else {
  // Owner can delete draft/rejected
  $canDelete = ((int)$report['user_id'] === (int)$user['id']) && in_array($report['status'], ['draft','rejected'], true);
}

if (!$canDelete) {
  http_response_code(403);
  flash_set('danger', 'You are not allowed to delete this report.');
  redirect('report/list.php');
}

try {
  $pdo->beginTransaction();
  // Rely on ON DELETE CASCADE for related tables like report_reviews
  $del = $pdo->prepare('DELETE FROM reports WHERE id = ?');
  $del->execute([$id]);
  $pdo->commit();
  flash_set('success', 'Report deleted successfully.');
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  flash_set('danger', 'Failed to delete report: ' . $e->getMessage());
}

redirect('report/list.php');
