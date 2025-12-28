<?php
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/helpers.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/validators.php';
require_once __DIR__ . '/../../lib/report_model.php';
require_login();
verify_csrf();
header('Content-Type: application/json; charset=UTF-8');
$pdo = db();
$user = current_user();

$reportId = (int)($_POST['report_id'] ?? 0);
if ($reportId <= 0) { json_response(['status'=>'error','message'=>'Invalid report id'], 400); }

if (empty($_FILES['file'])) { json_response(['status'=>'error','message'=>'No file'], 400); }
$file = $_FILES['file'];
if (!v_file_ok($file)) { json_response(['status'=>'error','message'=>'Invalid file'], 400); }

$orig = $file['name'];
$ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
$rand = bin2hex(random_bytes(6));
$stored = date('Ymd_His')."_".$rand.'.'.$ext;
$dest = UPLOAD_DIR . DIRECTORY_SEPARATOR . $stored;
if (!is_dir(UPLOAD_DIR)) { @mkdir(UPLOAD_DIR, 0775, true); }

if (!move_uploaded_file($file['tmp_name'], $dest)) { json_response(['status'=>'error','message'=>'Upload failed'], 500); }
$mime = @mime_content_type($dest) ?: null;
$size = filesize($dest) ?: 0;

try {
  $attId = saveAttachment($pdo, $reportId, $orig, $stored, $mime, (int)$size);
  json_response(['status'=>'ok','message'=>'Uploaded','data'=>['attachment_id'=>$attId,'file_name'=>$orig,'stored'=>$stored,'mime'=>$mime,'size'=>$size]]);
} catch (Throwable $e) {
  @unlink($dest);
  json_response(['status'=>'error','message'=>'DB error'], 500);
}
