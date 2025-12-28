<?php
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/helpers.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/config.php';
require_role(['super_admin']);
$pdo = db();

// Ensure backup directory
$backupDir = BASE_PATH . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'backups';
if (!is_dir($backupDir)) { @mkdir($backupDir, 0775, true); }
$fname = 'backup_' . date('Ymd_His') . '.sql';
$fpath = $backupDir . DIRECTORY_SEPARATOR . $fname;

// Try mysqldump first (if available in PATH)
function try_mysqldump($fpath){
  $bin = 'mysqldump';
  $host = APP_DB_HOST; $user = APP_DB_USER; $pass = APP_DB_PASS; $db = APP_DB_NAME;
  $passArg = $pass !== '' ? " -p\"$pass\"" : '';
  $cmd = $bin . " -h \"$host\" -u \"$user\"$passArg \"$db\" > \"$fpath\"";
  // On Windows PowerShell, use cmd /c
  if (stripos(PHP_OS, 'WIN') === 0) { $cmd = 'cmd /c ' . $cmd; }
  @exec($cmd, $o, $code);
  return file_exists($fpath) && filesize($fpath) > 0;
}

$ok = false;
try { $ok = try_mysqldump($fpath); } catch (Throwable $e) { $ok = false; }

if (!$ok) {
  // Fallback: generate SQL via PDO
  $tables = [
    'units','users','reports','attachments','audit_logs','glossary','analytics_cache','report_reviews','email_logs'
  ];
  $sqlOut = "SET NAMES utf8mb4;\nSET time_zone='+00:00';\n";
  foreach ($tables as $t) {
    // DDL
    $ddlSt = $pdo->query("SHOW CREATE TABLE `$t`");
    $ddl = $ddlSt->fetch();
    if (!empty($ddl['Create Table'])) { $sqlOut .= "\nDROP TABLE IF EXISTS `$t`;\n".$ddl['Create Table'].";\n\n"; }
    // DML
    $rs = $pdo->query("SELECT * FROM `$t`");
    while ($row = $rs->fetch(PDO::FETCH_ASSOC)) {
      $cols = array_map(fn($c)=>"`$c`", array_keys($row));
      $vals = array_map(fn($v)=> is_null($v)?'NULL':("'".str_replace("'","''", (string)$v)."'"), array_values($row));
      $sqlOut .= "INSERT INTO `$t` (".implode(',',$cols).") VALUES (".implode(',',$vals).");\n";
    }
    $sqlOut .= "\n";
  }
  file_put_contents($fpath, $sqlOut);
}

// Force download
header('Content-Type: application/sql');
header('Content-Disposition: attachment; filename="'.$fname.'"');
header('Content-Length: '.filesize($fpath));
readfile($fpath);
exit;
