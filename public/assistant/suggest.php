<?php
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/helpers.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_login();
verify_csrf();

$pdo = db();
$user = current_user();

// Analyze last 3 approved/submitted reports for the current unit
$st = $pdo->prepare("SELECT period_quarter, period_year, data_json FROM reports WHERE unit_id = ? AND status IN ('submitted','approved') ORDER BY period_year DESC, period_quarter DESC LIMIT 4");
$st->execute([$user['unit_id']]);
$rows = $st->fetchAll();
$avg = 0; $count=0; $series=[];
foreach ($rows as $r) {
  $d = json_decode($r['data_json'] ?? '{}', true);
  if (($d['sec1_total_issued'] ?? 0) > 0) {
    $count++;
    $hp = (int)($d['sec1_issued_in_hindi'] ?? 0) * 100 / (int)$d['sec1_total_issued'];
    $avg += $hp;
    $series[] = ['q'=>$r['period_quarter'],'y'=>$r['period_year'],'hp'=>round($hp,2)];
  }
}
$avg = $count ? round($avg/$count, 2) : null;

$tips = [];
if ($avg !== null) {
  $tips[] = "पिछली 3 तिमाहियों का औसत हिंदी उपयोग = {$avg}%";
  if ($avg < 70) { $tips[] = 'लक्ष्य 70% पाने हेतु हिंदी उत्तर दर बढ़ाएँ।'; }
}

// Deviation messages across up to 4 quarters
if (count($series) >= 2) {
  $curr = $series[0]['hp']; $prev = $series[1]['hp'];
  $delta = round($curr - $prev, 2);
  if (abs($delta) >= 10) {
    $tips[] = ($delta < 0)
      ? 'चेतावनी: इस तिमाही हिंदी उपयोग में 10% से अधिक गिरावट।'
      : 'उन्नति: इस तिमाही हिंदी उपयोग में 10% से अधिक वृद्धि।';
  }
}

// Suggest a numeric target for sec1_issued_in_hindi based on average percentage and most recent total
$suggestHindi = null;
if ($avg !== null && isset($rows[0])) {
  $last = json_decode($rows[0]['data_json'] ?? '{}', true) ?: [];
  $t = (int)($last['sec1_total_issued'] ?? 0);
  if ($t > 0) $suggestHindi = (int)round(($avg/100.0) * $t);
}

json_response([
  'ok' => true,
  'suggestions' => [
    'sec1_issued_in_hindi' => $suggestHindi,
    'remarks' => $avg !== null ? "Average of last 3 quarters = {$avg}." : 'No prior data found.'
  ],
  'tips' => $tips,
]);
