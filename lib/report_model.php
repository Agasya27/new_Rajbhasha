<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';

/**
 * saveDraft: Inserts or updates a draft report. If $reportId is null, inserts new; else updates existing when owned by user.
 * Returns array: ['ok'=>true, 'id'=>int]
 */
function saveDraft(PDO $pdo, array $user, ?int $reportId, array $formData): array {
  $now = date('Y-m-d H:i:s');
  $payload = json_encode($formData, JSON_UNESCAPED_UNICODE);
  $q = (int)($formData['period_quarter'] ?? 0);
  $y = (int)($formData['period_year'] ?? 0);
  if ($q < 1 || $q > 4) { $q = (int)ceil(date('n')/3); }
  if ($y < 2000) { $y = (int)date('Y'); }

  if ($reportId) {
    // Ensure ownership or super_admin
    $st = $pdo->prepare('SELECT id, user_id FROM reports WHERE id=? LIMIT 1');
    $st->execute([$reportId]);
    $r = $st->fetch();
    if (!$r) return ['ok'=>false,'error'=>'Report not found'];
    if ($user['role'] !== 'super_admin' && (int)$r['user_id'] !== (int)$user['id']) return ['ok'=>false,'error'=>'Forbidden'];
    $up = $pdo->prepare('UPDATE reports SET status="draft", period_quarter=?, period_year=?, data_json=?, updated_at=? WHERE id=?');
    $up->execute([$q,$y,$payload,$now,$reportId]);
    return ['ok'=>true,'id'=>$reportId];
  }

  $ins = $pdo->prepare('INSERT INTO reports(user_id, unit_id, status, period_quarter, period_year, data_json, updated_at) VALUES(?,?,?,?,?,?,?)');
  $ins->execute([$user['id'],$user['unit_id'],'draft',$q,$y,$payload,$now]);
  return ['ok'=>true,'id'=>(int)$pdo->lastInsertId()];
}

/**
 * submitReport: validates and marks as submitted; returns ['ok'=>true,'id'=>int]
 */
function submitReport(PDO $pdo, array $user, int $reportId, array $formData): array {
  // Minimal validation: Section 1 constraints
  $total = (int)($formData['total_issued'] ?? $formData['sec1_total_issued'] ?? 0);
  $h = (int)($formData['issued_in_hindi'] ?? $formData['sec1_issued_in_hindi'] ?? 0);
  $he = (int)($formData['issued_english_only'] ?? $formData['sec1_issued_english_only'] ?? 0);
  $ho = (int)($formData['issued_hindi_only'] ?? $formData['sec1_issued_hindi_only'] ?? 0);
  if ($ho > $h) return ['ok'=>false,'error'=>'केवल हिंदी, हिंदी में निर्गत से अधिक नहीं हो सकती।'];
  if (($h + $he) > $total) return ['ok'=>false,'error'=>'हिंदी + केवल अंग्रेज़ी, कुल निर्गत से अधिक नहीं हो सकते।'];

  $payload = json_encode($formData, JSON_UNESCAPED_UNICODE);
  $q = (int)($formData['period_quarter'] ?? 0);
  $y = (int)($formData['period_year'] ?? 0);
  if ($q < 1 || $q > 4) { $q = (int)ceil(date('n')/3); }
  if ($y < 2000) { $y = (int)date('Y'); }

  $st = $pdo->prepare('UPDATE reports SET status="submitted", period_quarter=?, period_year=?, data_json=?, updated_at=NOW() WHERE id=? AND (user_id=? OR ?="super_admin")');
  $st->execute([$q,$y,$payload,$reportId,$user['id'],$user['role']]);
  return ['ok'=>true,'id'=>$reportId];
}

function getReport(PDO $pdo, int $id): ?array {
  $st = $pdo->prepare('SELECT r.*, u.name as unit_name, us.name as user_name FROM reports r JOIN units u ON u.id=r.unit_id JOIN users us ON us.id=r.user_id WHERE r.id=?');
  $st->execute([$id]);
  $r = $st->fetch();
  if (!$r) return null;
  $r['data'] = json_decode($r['data_json'] ?? '[]', true) ?: [];
  // attachments
  try {
    $at = $pdo->prepare('SELECT id, file_name, file_path, mime_type, file_size, created_at FROM attachments WHERE report_id=? ORDER BY id DESC');
    $at->execute([$id]);
    $r['attachments'] = $at->fetchAll() ?: [];
  } catch (Throwable $e) { $r['attachments'] = []; }
  return $r;
}

function saveAttachment(PDO $pdo, int $reportId, string $origName, string $storedName, ?string $mime, int $size): int {
  $ins = $pdo->prepare('INSERT INTO attachments(report_id,file_name,file_path,mime_type,file_size,created_at) VALUES(?,?,?,?,?,NOW())');
  $ins->execute([$reportId,$origName,$storedName,$mime,$size]);
  return (int)$pdo->lastInsertId();
}
