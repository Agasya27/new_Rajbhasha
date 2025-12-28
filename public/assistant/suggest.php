<?php
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/helpers.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_login();
verify_csrf();

try {
  $pdo = db();
  $user = current_user();
  if (!$user) {
    json_response(['ok'=>false,'error'=>'User session missing. Please log in again.'], 401);
  }

  $snapshot = [];
  if (!empty($_POST['snapshot'])) {
    $decoded = json_decode((string)$_POST['snapshot'], true);
    if (is_array($decoded)) { $snapshot = $decoded; }
  }

  $parseNumber = static function ($value): float {
    if (is_array($value)) { $value = implode(' ', $value); }
    if (!is_string($value)) { $value = (string)$value; }
    if ($value === '') { return 0.0; }
    if (preg_match('/-?\d+(?:[\.,]\d+)?/', $value, $m)) {
      return (float)str_replace(',', '.', $m[0]);
    }
    return 0.0;
  };

  $unitId = $user['unit_id'] ?? null;
  if (!$unitId) {
    json_response([
      'ok' => true,
      'suggestions' => [],
      'tips' => ['अपने प्रोफ़ाइल में यूनिट असाइन करें ताकि सुझाव मिल सकें।']
    ]);
  }

  // Analyze last 3 approved/submitted reports for the current unit
  $st = $pdo->prepare("SELECT period_quarter, period_year, data_json FROM reports WHERE unit_id = ? AND status IN ('submitted','approved') ORDER BY period_year DESC, period_quarter DESC LIMIT 4");
  $st->execute([$unitId]);
  $rows = $st->fetchAll();
  $avg = 0; $count=0; $series=[];
  foreach ($rows as $r) {
    $d = json_decode($r['data_json'] ?? '{}', true);
    if (($d['sec1_total_issued'] ?? 0) > 0) {
      $count++;
      $base = (int)$d['sec1_total_issued'];
      $hi = (int)($d['sec1_issued_in_hindi'] ?? 0);
      $hp = $base > 0 ? ($hi * 100 / $base) : 0;
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

  // Fallback: use current form snapshot when historical data unavailable or incomplete
  if ($suggestHindi === null && !empty($snapshot)) {
    $totalSnap = (int)round($parseNumber($snapshot['sec1_total_issued'] ?? 0));
    if ($totalSnap > 0) {
      $existingSnap = (int)round($parseNumber($snapshot['sec1_issued_in_hindi'] ?? 0));
      $targetBase = (int)round($totalSnap * 0.7);
      $suggestHindi = max($existingSnap, $targetBase);
      $tips[] = 'ऐतिहासिक डेटा नहीं मिला। वर्तमान इनपुट के आधार पर 70% हिंदी लक्ष्य सुझाया गया है।';
    }
  }

  // Snapshot validations
  if (!empty($snapshot)) {
    $notReplied = $parseNumber($snapshot['sec2_not_replied_in_hindi'] ?? 0);
    $reason = trim((string)($snapshot['sec2_reason_not_replied'] ?? ''));
    if ($notReplied > 0 && $reason === '') {
      $tips[] = 'Section 2: "कारण" कॉलम भरें क्योंकि हिंदी में उत्तर नहीं हैं.';
    }
    $hOnly = (int)round($parseNumber($snapshot['sec1_issued_hindi_only'] ?? 0));
    $hTotal = (int)round($parseNumber($snapshot['sec1_issued_in_hindi'] ?? 0));
    if ($hOnly > $hTotal && $hTotal > 0) {
      $tips[] = 'Section 1: केवल हिंदी दस्तावेज़ कुल हिंदी निर्गत से अधिक नहीं होने चाहिए.';
    }
  }

  if (empty($tips)) {
    $tips[] = 'कोई हालिया डेटा नहीं मिला। पिछले तिमाहियों की रिपोर्ट सबमिट करें ताकि स्मार्ट सुझाव बेहतर हों।';
  }

  $suggestions = [];
  if ($suggestHindi !== null) {
    $suggestions['sec1_issued_in_hindi'] = $suggestHindi;
  }

  json_response([
    'ok' => true,
    'suggestions' => $suggestions,
    'tips' => $tips,
    'meta' => [
      'history_avg' => $avg,
      'history_samples' => count($series)
    ],
  ]);
} catch (Throwable $e) {
  error_log('Smart suggestions error: '.$e->getMessage().' @ '.$e->getFile().':'.$e->getLine());
  json_response(['ok'=>false,'error'=>'Suggestion service failed: '.$e->getMessage()], 500);
}
