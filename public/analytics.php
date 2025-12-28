<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/db.php';
require_login();
$pdo = db();

$unitId = (int)($_GET['unit_id'] ?? 0);
$year = (int)($_GET['year'] ?? date('Y'));

// Fetch data for charts
$where = "WHERE status IN ('submitted','approved') AND period_year = ?";
$params = [$year];
if ($unitId) { $where .= ' AND unit_id = ?'; $params[] = $unitId; }
$st = $pdo->prepare("SELECT unit_id, period_quarter, period_year, data_json FROM reports $where ORDER BY period_quarter ASC");
$st->execute($params);
$rows = $st->fetchAll();

$line = [1=>0,2=>0,3=>0,4=>0]; $lineN=[1=>0,2=>0,3=>0,4=>0];
$hi=0; $en=0;
$dept = [];
foreach ($rows as $r) {
  $d = json_decode($r['data_json'] ?? '{}', true);
  $q = (int)$r['period_quarter'];
  if (($d['sec1_total_issued'] ?? 0) > 0) {
    $hp = (int)($d['sec1_issued_in_hindi'] ?? 0) * 100 / max(1,(int)$d['sec1_total_issued']);
    $line[$q] += $hp; $lineN[$q]++;
    $hi += (int)($d['sec1_issued_in_hindi'] ?? 0);
    $en += (int)($d['sec1_issued_english_only'] ?? 0);
  }
  // Section 4 has categories; approximate dept as categories 1..3
  for($i=1;$i<=3;$i++) {
    $dept[$i]['h'] = ($dept[$i]['h'] ?? 0) + (int)($d["sec4_hindi_$i"] ?? 0);
    $dept[$i]['e'] = ($dept[$i]['e'] ?? 0) + (int)($d["sec4_english_$i"] ?? 0);
    $dept[$i]['b'] = ($dept[$i]['b'] ?? 0) + (int)($d["sec4_bilingual_$i"] ?? 0);
  }
}
for($i=1;$i<=4;$i++){ $line[$i] = $lineN[$i] ? round($line[$i]/$lineN[$i],2) : 0; }

// Export to PDF
if (isset($_GET['action']) && $_GET['action']==='pdf') {
  if (!class_exists('Dompdf\\Dompdf')) { die('Dompdf not installed.'); }
  ob_start();
  echo '<meta charset="UTF-8"><style>body{font-family: DejaVu Sans, sans-serif} table{width:100%;border-collapse:collapse} td,th{border:1px solid #ccc;padding:6px}</style>';
  echo '<h3>Analytics & Insights</h3>';
  echo '<p>Year: '.(int)$year.'</p>';
  echo '<table><tr><th>Quarter</th><th>Avg Hindi %</th></tr>';
  for($i=1;$i<=4;$i++){ echo '<tr><td>Q'.$i.'</td><td>'.$line[$i].'%</td></tr>'; }
  echo '</table>';
  $html = ob_get_clean();
  $dompdf = new Dompdf\Dompdf(['defaultFont'=>'DejaVu Sans']);
  $dompdf->loadHtml($html, 'UTF-8');
  $dompdf->setPaper('A4');
  $dompdf->render();
  $dompdf->stream('analytics_'.$year.'.pdf', ['Attachment'=>true]);
  exit;
}

include __DIR__ . '/../templates/header.php';
?>
<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= app_base_url('dashboard.php')?>">Dashboard</a></li>
    <li class="breadcrumb-item active" aria-current="page">Analytics & Insights</li>
  </ol>
 </nav>

<div class="card mb-3">
  <div class="card-body">
    <form method="get" class="row g-2">
      <div class="col-md-3">
        <label class="form-label">Year</label>
        <input type="number" class="form-control" name="year" value="<?= (int)$year ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">Unit</label>
        <select class="form-select" name="unit_id">
          <option value="0">All Units</option>
          <?php foreach($pdo->query('SELECT id, name FROM units ORDER BY name') as $u): ?>
            <option value="<?= (int)$u['id'] ?>" <?= $unitId==(int)$u['id']?'selected':'' ?>><?= esc($u['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-5 d-flex align-items-end gap-2">
        <button class="btn btn-primary">Apply</button>
        <a class="btn btn-outline-secondary" href="<?= app_base_url('analytics.php?action=pdf&year='.(int)$year.($unitId?'&unit_id='.$unitId:'') )?>">Export Analytics PDF</a>
      </div>
    </form>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="card h-100"><div class="card-body">
      <h6 class="card-title">Quarterly Hindi-usage</h6>
      <canvas id="lineChart"></canvas>
    </div></div>
  </div>
  <div class="col-lg-6">
    <div class="card h-100"><div class="card-body">
      <h6 class="card-title">Hindi vs English</h6>
      <canvas id="pieChart"></canvas>
    </div></div>
  </div>
  <div class="col-12">
    <div class="card"><div class="card-body">
      <h6 class="card-title">Dept-wise (Sec 4)</h6>
      <canvas id="barChart"></canvas>
    </div></div>
  </div>
</div>

<script>
const lineData = [<?= $line[1] ?>, <?= $line[2] ?>, <?= $line[3] ?>, <?= $line[4] ?>];
const pieData = [<?= (int)$hi ?>, <?= (int)$en ?>];
const deptH = [<?= (int)($dept[1]['h']??0) ?>, <?= (int)($dept[2]['h']??0) ?>, <?= (int)($dept[3]['h']??0) ?>];
const deptE = [<?= (int)($dept[1]['e']??0) ?>, <?= (int)($dept[2]['e']??0) ?>, <?= (int)($dept[3]['e']??0) ?>];
const deptB = [<?= (int)($dept[1]['b']??0) ?>, <?= (int)($dept[2]['b']??0) ?>, <?= (int)($dept[3]['b']??0) ?>];

new Chart(document.getElementById('lineChart'), { type: 'line', data: { labels:['Q1','Q2','Q3','Q4'], datasets:[{ label:'% Hindi', data: lineData, borderColor:'#198754'}] }, options: { scales:{ y:{ beginAtZero:true, max:100 } } } });
new Chart(document.getElementById('pieChart'), { type: 'pie', data: { labels:['Hindi','English'], datasets:[{ data: pieData, backgroundColor:['#198754','#0d6efd'] }] } });
new Chart(document.getElementById('barChart'), { type: 'bar', data: { labels:['क','ख','ग'], datasets:[{ label:'Hindi', data: deptH, backgroundColor:'#198754'},{ label:'English', data: deptE, backgroundColor:'#0d6efd'},{ label:'Bilingual', data: deptB, backgroundColor:'#6c757d'}] } });
</script>

<?php include __DIR__ . '/../templates/footer.php'; ?>
