<?php
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/helpers.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/report_model.php';
require_login();
verify_csrf();
$pdo = db();
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo 'Method Not Allowed'; exit; }

$reportId = (int)($_POST['report_id'] ?? 0);
if ($reportId <= 0) { flash_set('danger','Invalid report'); redirect('report/new.php'); }

// Build form data from POST (except meta keys)
$data = $_POST; unset($data['_csrf'],$data['report_id'],$data['submit']);

$res = submitReport($pdo, $user, $reportId, $data);
if (!$res['ok']) { flash_set('danger', $res['error'] ?? 'सबमिट विफल'); redirect('report/edit.php?id='.$reportId); }

flash_set('success','रिपोर्ट जमा / Report submitted');
redirect('report/view.php?id='.$reportId);
