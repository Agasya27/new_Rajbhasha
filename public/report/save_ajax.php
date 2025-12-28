<?php
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/helpers.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/report_model.php';
require_login();
header('Content-Type: application/json; charset=UTF-8');
verify_csrf();
$pdo = db();
$user = current_user();

// Expect JSON body
$raw = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body)) { json_response(['status'=>'error','message'=>'Invalid JSON'], 400); }
$reportId = isset($body['report_id']) ? (int)$body['report_id'] : null;
unset($body['report_id']);

try {
  $res = saveDraft($pdo, $user, $reportId, $body);
  if (!$res['ok']) json_response(['status'=>'error','message'=>$res['error'] ?? 'Failed']);
  json_response(['status'=>'ok','message'=>'ड्राफ्ट सुरक्षित','data'=>['report_id'=>$res['id'],'saved_at'=>date('H:i')]]);
} catch (Throwable $e) {
  json_response(['status'=>'error','message'=>'Server error'], 500);
}
