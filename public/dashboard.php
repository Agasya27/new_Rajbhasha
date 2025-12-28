<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/db.php';
require_login();
$pdo = db();

// Basic stats
$pending = (int)$pdo->query("SELECT COUNT(*) FROM reports WHERE status='pending' OR status='submitted'")->fetchColumn();
$approved = (int)$pdo->query("SELECT COUNT(*) FROM reports WHERE status='approved'")->fetchColumn();
$submitted = (int)$pdo->query("SELECT COUNT(*) FROM reports WHERE status IN ('submitted','approved','rejected')")->fetchColumn();

// Hindi usage ratio: derive from report JSON averages if available
$rows = $pdo->query("SELECT period_quarter, period_year, unit_id, data_json FROM reports WHERE status IN ('submitted','approved') ORDER BY period_year DESC, period_quarter DESC, id DESC LIMIT 40")->fetchAll();
$hindiPercent = 0; $n=0; $trend=[]; $unitTop=[]; $cu = current_user();
foreach ($rows as $r) {
  $d = json_decode($r['data_json'] ?? '{}', true);
  if (isset($d['sec1_total_issued']) && $d['sec1_total_issued']>0) {
    $n++;
    $hp = (int)($d['sec1_issued_in_hindi'] ?? 0) * 100 / max((int)$d['sec1_total_issued'],1);
    $hindiPercent += $hp;
    if ((int)$r['unit_id'] === (int)$cu['unit_id']) {
      $trend[] = ['q'=>$r['period_quarter'],'y'=>$r['period_year'],'hp'=>round($hp,2)];
    }
    $unitTop[$r['unit_id']][] = $hp;
  }
}
$hindiPercent = $n ? round($hindiPercent/$n, 2) : 0;
// Aggregate top units by avg
$topUnits = [];
foreach ($unitTop as $uid=>$arr) { $topUnits[] = ['unit'=>$uid,'avg'=>round(array_sum($arr)/max(count($arr),1),2)]; }
usort($topUnits, fn($a,$b)=> $b['avg'] <=> $a['avg']);
$topUnits = array_slice($topUnits,0,5);

include __DIR__ . '/../templates/header.php';
?>
<div class="row g-3">
  <div class="col-md-3">
    <div class="card text-bg-warning"><div class="card-body">
      <div class="h5">लंबित रिपोर्ट / Pending</div>
      <div class="display-6"><?= $pending ?></div>
    </div></div>
  </div>
  <div class="col-md-3">
    <div class="card text-bg-success"><div class="card-body">
      <div class="h5">स्वीकृत / Approved</div>
      <div class="display-6"><?= $approved ?></div>
    </div></div>
  </div>
  <div class="col-md-3">
    <div class="card text-bg-primary"><div class="card-body">
      <div class="h5">जमा / Submitted</div>
      <div class="display-6"><?= $submitted ?></div>
    </div></div>
  </div>
  <div class="col-md-3">
    <div class="card"><div class="card-body">
      <div class="h6">हिंदी उपयोग प्रतिशत (औसत)</div>
      <div class="display-6"><?= $hindiPercent ?>%</div>
    </div></div>
  </div>
</div>

<div class="card mt-4">
  <div class="card-body">
    <h5 class="card-title mb-3">हिंदी उपयोग ग्राफ / Hindi Usage</h5>
    <canvas id="usageChart" height="100"></canvas>
  </div>
</div>

<script>
const ctx = document.getElementById('usageChart');
const chart = new Chart(ctx, {
  type: 'line',
  data: {
    labels: [<?php $labels=[]; foreach (array_reverse(array_slice($trend,0,4)) as $t){ $labels[]='"Q'.$t['q'].' '.$t['y'].'"'; } echo implode(',', $labels) ?: '"Q"'; ?>],
    datasets: [{label:'% हिंदी', data:[<?php $vals=[]; foreach (array_reverse(array_slice($trend,0,4)) as $t){ $vals[]=$t['hp']; } echo implode(',', $vals) ?: $hindiPercent; ?>], borderColor:'#198754'}]
  },
  options: { scales: { y: { beginAtZero: true, max: 100 } } }
});
</script>

<div class="card mt-4">
  <div class="card-body">
    <h5 class="card-title mb-3">Top Units (Avg Hindi %)</h5>
    <div class="row">
      <?php foreach ($topUnits as $tu): $uname = $pdo->prepare('SELECT name FROM units WHERE id=?'); $uname->execute([$tu['unit']]); $un=$uname->fetchColumn(); ?>
      <div class="col-md-6 col-lg-4 mb-2">
        <div class="d-flex justify-content-between small"><span><?= esc($un ?: ('Unit '.$tu['unit'])) ?></span><span><?= $tu['avg'] ?>%</span></div>
        <div class="progress"><div class="progress-bar bg-success" role="progressbar" style="width: <?= (int)$tu['avg'] ?>%"></div></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>
