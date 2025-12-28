<?php
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/helpers.php';
require_once __DIR__ . '/../../lib/db.php';
require_login();
require_once __DIR__ . '/../../lib/report_model.php';
$pdo = db();
$user = current_user();

$id = (int)($_GET['id'] ?? 0);
$r = getReport($pdo, $id);
if (!$r) { redirect('list.php'); }

// PDF export via Dompdf
if (isset($_GET['action']) && $_GET['action']==='pdf') {
  if (!class_exists('Dompdf\\Dompdf')) { die('Dompdf not installed. Run composer install.'); }
  $data = $r['data'] ?? [];
  $html = '<html><meta charset="UTF-8"><style>body{font-family: DejaVu Sans, sans-serif;} table{width:100%;border-collapse:collapse} td,th{border:1px solid #ccc;padding:6px}</style><body>';
  $html .= '<h3 style="text-align:center">WCL Rajbhasha Report #'.$r['id'].'</h3>';
  $html .= '<p>Unit: '.htmlspecialchars($r['unit_name']).' | Period: Q'.$r['period_quarter'].' '.$r['period_year'].' | By: '.htmlspecialchars($r['user_name']).'</p>';
  $html .= '<table><tr><th>Section</th><th>Key</th><th>Value</th></tr>';
  foreach ($data as $k=>$v) { $html.='<tr><td>'.htmlspecialchars(substr($k,0,5)).'</td><td>'.htmlspecialchars($k).'</td><td>'.htmlspecialchars((string)$v).'</td></tr>'; }
  $html .= '</table></body></html>';
  $dompdf = new Dompdf\Dompdf(['defaultFont'=>'DejaVu Sans']);
  $dompdf->loadHtml($html, 'UTF-8');
  $dompdf->setPaper('A4');
  $dompdf->render();
  $dompdf->stream('report_'.$r['id'].'.pdf', ['Attachment'=>true]);
  exit;
}

$data = $r['data'] ?? [];
$attachments = $r['attachments'] ?? [];

include __DIR__ . '/../../templates/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4>रिपोर्ट #<?= (int)$r['id'] ?> (<?= esc($r['status']) ?>)</h4>
  <div>
    <a class="btn btn-sm btn-outline-secondary" href="<?= app_base_url('report/list.php') ?>">Back</a>
  <a class="btn btn-sm btn-primary" href="<?= app_base_url('report/export_pdf.php?id='.(int)$r['id']) ?>">Download PDF</a>
  </div>
</div>

<div class="card mb-3"><div class="card-body">
  <div><strong>Unit:</strong> <?= esc($r['unit_name']) ?> | <strong>Period:</strong> Q<?= (int)$r['period_quarter'] ?> <?= (int)$r['period_year'] ?> | <strong>By:</strong> <?= esc($r['user_name']) ?></div>
</div></div>

<div class="card"><div class="card-body">
  <h6>डेटा / Data (JSON)</h6>
  <pre class="small bg-light p-2 border" style="max-height:300px;overflow:auto;"><?= esc(json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) ?></pre>
  <h6>Attachments</h6>
  <ul>
    <?php foreach ($attachments as $a): ?>
    <li><a href="<?= app_base_url('uploads/'.esc($a['file_path'])) ?>" target="_blank"><?= esc($a['file_name']) ?></a></li>
    <?php endforeach; ?>
  </ul>
</div></div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
