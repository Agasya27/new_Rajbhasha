<?php
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/helpers.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_login();
verify_csrf();
header('Content-Type: application/json; charset=UTF-8');
$pdo = db();
$user = current_user();

$attId = (int)($_POST['attachment_id'] ?? 0);
if ($attId <= 0) { json_response(['status'=>'error','message'=>'Invalid attachment id'], 400); }

$st = $pdo->prepare('SELECT a.id, a.file_path, r.id AS report_id, r.user_id, r.status FROM attachments a JOIN reports r ON r.id=a.report_id WHERE a.id=? LIMIT 1');
$st->execute([$attId]);
$row = $st->fetch();
if (!$row) { json_response(['status'=>'error','message'=>'Not found'], 404); }

$can = false;
if ($user['role']==='super_admin') { $can = ($row['status'] !== 'approved'); }
else { $can = ((int)$row['user_id']===(int)$user['id'] && $row['status']==='draft'); }
if (!$can) { json_response(['status'=>'error','message'=>'Forbidden'], 403); }

$del = $pdo->prepare('DELETE FROM attachments WHERE id=?');
$del->execute([$attId]);

$path = UPLOAD_DIR . DIRECTORY_SEPARATOR . $row['file_path'];
if (is_file($path)) { @unlink($path); }

json_response(['status'=>'ok','message'=>'Deleted']);
