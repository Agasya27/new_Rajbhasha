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

if (empty($_FILES['file'])) { json_response(['status'=>'error','message'=>'No file provided'], 400); }
$file = $_FILES['file'];
$errorCode = $file['error'] ?? UPLOAD_ERR_NO_FILE;
if ($errorCode !== UPLOAD_ERR_OK) {
  $friendly = match ($errorCode) {
    UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'फ़ाइल 10MB सीमा या PHP upload_max_filesize से अधिक है। कृपया छोटी फ़ाइल चुनें।',
    UPLOAD_ERR_PARTIAL => 'फ़ाइल पूरी अपलोड नहीं हो सकी। दोबारा कोशिश करें।',
    UPLOAD_ERR_NO_FILE => 'कोई फ़ाइल प्राप्त नहीं हुई।',
    UPLOAD_ERR_NO_TMP_DIR => 'सर्वर पर अस्थायी फ़ोल्डर उपलब्ध नहीं है। व्यवस्थापक से संपर्क करें।',
    UPLOAD_ERR_CANT_WRITE => 'सर्वर डिस्क पर फ़ाइल सेव नहीं हो सकी। व्यवस्थापक से संपर्क करें।',
    UPLOAD_ERR_EXTENSION => 'सर्वर एक्सटेंशन ने अपलोड रोका।',
    default => 'अज्ञात अपलोड त्रुटि (कोड '.$errorCode.').'
  };
  json_response(['status'=>'error','message'=>$friendly], 400);
}

$size = (int)($file['size'] ?? 0);
if ($size <= 0) {
  json_response(['status'=>'error','message'=>'फ़ाइल खाली है।'], 400);
}
if ($size > 10 * 1024 * 1024) {
  json_response(['status'=>'error','message'=>'हर फ़ाइल के लिए अधिकतम सीमा 10MB है। कृपया आकार घटाएँ।'], 400);
}

$orig = $file['name'] ?? '';
$ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
$allowedExts = ['pdf','png','jpg','jpeg'];
if (!in_array($ext, $allowedExts, true)) {
  json_response(['status'=>'error','message'=>'केवल PDF, JPG या PNG फ़ाइलें स्वीकार हैं।'], 400);
}

$tmpPath = $file['tmp_name'] ?? '';
if (!is_uploaded_file($tmpPath)) {
  json_response(['status'=>'error','message'=>'अस्थायी फ़ाइल नहीं मिली। दोबारा अपलोड करें।'], 500);
}

$mime = @mime_content_type($tmpPath) ?: '';
$allowedMimes = ['application/pdf','application/x-pdf','image/png','image/jpeg','image/pjpeg','application/octet-stream'];
if ($mime && !in_array($mime, $allowedMimes, true)) {
  if (!($ext === 'pdf' && str_starts_with($mime, 'application/'))) {
    json_response(['status'=>'error','message'=>'फ़ाइल का प्रकार समर्थित नहीं है ('.$mime.').'], 400);
  }
}

$rand = bin2hex(random_bytes(6));
$stored = date('Ymd_His')."_".$rand.'.'.$ext;
$dest = UPLOAD_DIR . DIRECTORY_SEPARATOR . $stored;

if (!is_dir(UPLOAD_DIR)) {
  if (!@mkdir(UPLOAD_DIR, 0775, true)) {
    error_log('Upload: failed to create uploads directory at '.UPLOAD_DIR);
    json_response(['status'=>'error','message'=>'Uploads directory not writable. Contact admin.'], 500);
  }
}

if (!is_writable(UPLOAD_DIR)) {
  error_log('Upload: uploads directory not writable at '.UPLOAD_DIR);
  json_response(['status'=>'error','message'=>'Uploads directory not writable. Contact admin.'], 500);
}

if (!move_uploaded_file($tmpPath, $dest)) {
  $err = error_get_last();
  error_log('Upload: move_uploaded_file failed for '.$orig.' -> '.$dest.' :: '.json_encode(['err'=>$err,'tmp'=>$tmpPath]));
  json_response(['status'=>'error','message'=>'Upload failed: unable to store file.'], 500);
}
$mimeStored = @mime_content_type($dest) ?: null;
$sizeStored = filesize($dest) ?: 0;

try {
  $attId = saveAttachment($pdo, $reportId, $orig, $stored, $mimeStored, (int)$sizeStored);
  json_response(['status'=>'ok','message'=>'Uploaded','data'=>['attachment_id'=>$attId,'file_name'=>$orig,'stored'=>$stored,'mime'=>$mimeStored,'size'=>$sizeStored]]);
} catch (Throwable $e) {
  @unlink($dest);
  json_response(['status'=>'error','message'=>'DB error'], 500);
}
