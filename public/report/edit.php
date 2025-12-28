<?php
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/helpers.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_role(['officer','super_admin']);
$pdo = db();
$user = current_user();

$id = (int)($_GET['id'] ?? 0);
$st = $pdo->prepare('SELECT * FROM reports WHERE id=?');
$st->execute([$id]);
$report = $st->fetch();
if (!$report || !in_array($report['status'], ['draft','rejected'])) { redirect('report/list.php'); }
if ($user['role'] !== 'super_admin' && (int)$report['user_id'] !== (int)$user['id']) { http_response_code(403); exit('Forbidden'); }

// Update similar to new.php but UPDATE instead of INSERT
if (is_post() && isset($_POST['save'])) {
  verify_csrf();
  // Cross-field validations similar to new.php
  $intFields = ['sec1_total_issued','sec1_issued_in_hindi','sec1_issued_english_only','sec2_not_replied_in_hindi'];
  foreach ($intFields as $f) { if (isset($_POST[$f])) $_POST[$f] = (int)$_POST[$f]; }
  $total = (int)($_POST['sec1_total_issued'] ?? 0);
  $sumIssued = (int)($_POST['sec1_issued_in_hindi'] ?? 0) + (int)($_POST['sec1_issued_english_only'] ?? 0);
  if ($total>0 && $sumIssued > $total) {
  flash_set('danger','Section 1: Hindi + English-only cannot exceed Total issued.');
  redirect('report/edit.php?id='.$id);
  }
  if ((int)($_POST['sec2_not_replied_in_hindi'] ?? 0) > 0 && empty(trim((string)($_POST['sec2_reason_not_replied'] ?? '')))) {
  flash_set('danger','Please provide reason for items not replied in Hindi (Section 2).');
  redirect('report/edit.php?id='.$id);
  }

  $data = $_POST; unset($data['_csrf'],$data['save']);
  $payload = json_encode($data, JSON_UNESCAPED_UNICODE);
  $status = $_POST['save'] === 'final' ? 'submitted' : 'draft';
  $pdo->prepare('UPDATE reports SET status=?, data_json=?, updated_at=NOW(), period_quarter=?, period_year=? WHERE id=?')
      ->execute([$status,$payload,(int)($_POST['period_quarter']??$report['period_quarter']),(int)($_POST['period_year']??$report['period_year']),$id]);

  // Attachments upload (append)
  if (!empty($_FILES['attachments']['name'][0])) {
    $count = count($_FILES['attachments']['name']);
    for ($i=0; $i<$count; $i++) {
      if ($_FILES['attachments']['error'][$i] !== UPLOAD_ERR_OK) continue;
      if ($_FILES['attachments']['size'][$i] > 10*1024*1024) continue;
      $name = $_FILES['attachments']['name'][$i];
      $tmp = $_FILES['attachments']['tmp_name'][$i];
      $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
      if (!in_array($ext, ['pdf','png','jpg','jpeg'], true)) continue;
      $safeName = time().'_'.preg_replace('/[^A-Za-z0-9_\.-]/','_', $name);
      $dest = UPLOAD_DIR . DIRECTORY_SEPARATOR . $safeName;
      if (move_uploaded_file($tmp, $dest)) {
        $pdo->prepare('INSERT INTO attachments(report_id,file_name,file_path,mime_type,file_size) VALUES(?,?,?,?,?)')
            ->execute([$id,$name,$safeName,mime_content_type($dest) ?: null, filesize($dest)]);
      }
    }
  }

  flash_set('success', $status==='submitted' ? 'रिपोर्ट जमा / Report submitted' : 'ड्राफ्ट सुरक्षित / Draft saved');
  redirect('report/view.php?id='.$id);
}

// Decode data for prefill
$data = json_decode($report['data_json'] ?? '[]', true) ?: [];
include __DIR__ . '/../../templates/header.php';
?>
<h4>रिपोर्ट संपादन / Edit Report #<?= (int)$id ?></h4>
<form method="post" enctype="multipart/form-data">
  <?= csrf_input() ?>
  <!-- Minimal prefill for key fields; full form can be mirrored from new.php as needed -->
  <div class="row g-2 mb-3">
    <div class="col-md-2"><label class="form-label">Quarter</label><input class="form-control" name="period_quarter" type="number" value="<?= esc($report['period_quarter']) ?>"></div>
    <div class="col-md-2"><label class="form-label">Year</label><input class="form-control" name="period_year" type="number" value="<?= esc($report['period_year']) ?>"></div>
  </div>
  <div class="row g-2 mb-3">
    <div class="col-md-3"><label class="form-label">कुल निर्गत</label><input class="form-control" name="sec1_total_issued" type="number" value="<?= esc($data['sec1_total_issued']??0) ?>"></div>
    <div class="col-md-3"><label class="form-label">हिंदी में निर्गत</label><input class="form-control" name="sec1_issued_in_hindi" type="number" value="<?= esc($data['sec1_issued_in_hindi']??0) ?>"></div>
  </div>
  <div class="mb-3">
    <label class="form-label">Attachments</label>
    <input type="file" name="attachments[]" class="form-control" accept=".pdf,.jpg,.jpeg,.png" multiple>
  </div>
  <div class="d-flex gap-2">
    <button class="btn btn-secondary" name="save" value="draft">Save Draft</button>
    <button class="btn btn-primary" name="save" value="final">Submit Final</button>
  </div>
</form>
<?php include __DIR__ . '/../../templates/footer.php'; ?>
