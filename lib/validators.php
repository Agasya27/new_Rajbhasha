<?php
declare(strict_types=1);

function v_is_int($v, int $min = PHP_INT_MIN, int $max = PHP_INT_MAX): bool {
  if (!is_numeric($v)) return false;
  $i = (int)$v;
  return (string)$i === (string)$v || (is_float($v)+0 === 0.0) ? ($i >= $min && $i <= $max) : ($i >= $min && $i <= $max);
}

function v_required($v): bool { return !(is_null($v) || $v === '' || (is_string($v) && trim($v) === '')); }

function v_maxlen($v, int $max): bool { return mb_strlen((string)$v, 'UTF-8') <= $max; }

function v_file_ok(array $file, array $exts = ['pdf','png','jpg','jpeg'], int $maxBytes = 10485760): bool {
  if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return false;
  if (($file['size'] ?? 0) > $maxBytes) return false;
  $name = (string)($file['name'] ?? '');
  $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
  if (!in_array($ext, $exts, true)) return false;
  $f = $file['tmp_name'] ?? '';
  if (!is_file($f)) return false;
  $mime = @mime_content_type($f) ?: '';
  $okMimes = ['application/pdf','image/png','image/jpeg'];
  if (!in_array($mime, $okMimes, true)) return false;
  return true;
}

function v_err(string $msg): array { return ['ok'=>false,'error'=>$msg]; }
function v_ok(array $data = []): array { return ['ok'=>true,'data'=>$data]; }
