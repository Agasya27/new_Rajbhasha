<?php
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/helpers.php';
require_once __DIR__ . '/../../lib/db.php';
require_login();
$pdo = db();
$user = current_user();

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="reports.csv"');
echo "\xEF\xBB\xBF"; // UTF-8 BOM for Excel

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id) {
  // Export single report
  $st = $pdo->prepare('SELECT * FROM reports WHERE id=?');
  $st->execute([$id]);
  $rows = $st->fetchAll();
} else {
  // Export many depending on role
  if (in_array($user['role'], ['super_admin','reviewer','viewer'])) {
    $rows = $pdo->query('SELECT * FROM reports ORDER BY id DESC')->fetchAll();
  } else {
    $st = $pdo->prepare('SELECT * FROM reports WHERE user_id=? ORDER BY id DESC');
    $st->execute([$user['id']]);
    $rows = $st->fetchAll();
  }
}

$out = fopen('php://output', 'w');
fputcsv($out, ['id','unit_id','user_id','status','period_quarter','period_year','key','value']);
foreach ($rows as $r) {
  $data = json_decode($r['data_json'] ?? '{}', true) ?: [];
  if (!$data) {
    fputcsv($out, [$r['id'],$r['unit_id'],$r['user_id'],$r['status'],$r['period_quarter'],$r['period_year'],'','']);
    continue;
  }
  foreach ($data as $k=>$v) {
    fputcsv($out, [$r['id'],$r['unit_id'],$r['user_id'],$r['status'],$r['period_quarter'],$r['period_year'],$k,is_scalar($v)?$v:json_encode($v, JSON_UNESCAPED_UNICODE)]);
  }
}
fclose($out);
exit;
