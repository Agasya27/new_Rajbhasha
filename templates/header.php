<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/csrf.php';
header('Content-Type: text/html; charset=UTF-8');
$user = current_user();
$flash = flash_get_all();

// Resolve navbar brand logo from images folder(s)
$brandLogoDataUrl = '';
try {
  // Prefer project root assets first, then public assets
  $dirs = [
    BASE_PATH . '/assets/images',
    BASE_PATH . '/public/assets/images',
  ];
  $exts = ['png','jpg','jpeg','webp'];
  $picked = '';
  // 1) Explicit logo.ext if present
  foreach ($dirs as $d) {
    foreach ($exts as $e) {
      $f = $d . '/logo.' . $e;
      if (is_file($f)) { $picked = $f; break 2; }
    }
  }
  // 2) Any file starting with logo, brand or login (underscore/dash optional)
  if (!$picked) {
    foreach ($dirs as $d) {
      if (!is_dir($d)) continue;
      $cands = array_merge(
        glob($d.'/logo*.{png,jpg,jpeg,webp}', GLOB_BRACE) ?: [],
        glob($d.'/brand*.{png,jpg,jpeg,webp}', GLOB_BRACE) ?: [],
        glob($d.'/login*.{png,jpg,jpeg,webp}', GLOB_BRACE) ?: []
      );
      if (!empty($cands)) { $picked = $cands[0]; break; }
    }
  }
  // 3) Any image as last resort
  if (!$picked) {
    foreach ($dirs as $d) {
      if (!is_dir($d)) continue;
      $all = glob($d.'/*.{png,jpg,jpeg,webp}', GLOB_BRACE) ?: [];
      if (!empty($all)) { $picked = $all[0]; break; }
    }
  }

  if ($picked && is_file($picked)) {
    $ext = strtolower(pathinfo($picked, PATHINFO_EXTENSION));
    $mime = $ext === 'png' ? 'image/png' : ($ext === 'webp' ? 'image/webp' : 'image/jpeg');
    $data = @file_get_contents($picked);
    if ($data !== false) {
      $brandLogoDataUrl = 'data:' . $mime . ';base64,' . base64_encode($data);
    }
  }
} catch (Throwable $e) { /* ignore */ }
?>
<!doctype html>
<html lang="hi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= esc(csrf_token()) ?>">
  <title>WCL Rajbhasha Portal</title>
  <!-- Use local Bootstrap to avoid CDN tracking prevention issues -->
  <link href="<?= app_base_url('assets/vendor/bootstrap.min.css') ?>" rel="stylesheet">
  <link href="<?= app_base_url('assets/css/style.css') ?>" rel="stylesheet">
  <link href="<?= app_base_url('assets/css/print.css') ?>" rel="stylesheet" media="print">
  <script>
    (function(){
      try{ var m=document.querySelector('meta[name="csrf-token"]'); if(m){ window.CSRF_TOKEN=m.content; } }catch(e){}
    })();
  </script>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container-fluid">
    <a class="navbar-brand d-flex align-items-center gap-2" href="<?= app_base_url('dashboard.php')?>">
      <img src="<?= $brandLogoDataUrl ?: app_base_url('assets/images/wcl_logo.png') ?>" alt="WCL" style="height:28px;width:auto;object-fit:contain;" />
      <span>WCL Rajbhasha</span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarsExample" aria-controls="navbarsExample" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarsExample">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <?php if ($user): ?>
        <li class="nav-item"><a class="nav-link" href="<?= app_base_url('dashboard.php') ?>">डैशबोर्ड / Dashboard</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= app_base_url('report/list.php') ?>">रिपोर्ट्स / Reports</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= app_base_url('analytics.php') ?>">एनालिटिक्स / Analytics</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= app_base_url('assistant/index.php') ?>" title="Smart Rajbhasha Assistant">Assistant</a></li>
        <?php if (in_array($user['role'], ['super_admin','reviewer'])): ?>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">प्रशासन</a>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="<?= app_base_url('admin/users.php') ?>">Users</a></li>
            <?php if (in_array($user['role'], ['super_admin'])): ?>
            <li><a class="dropdown-item" href="<?= app_base_url('admin/units.php') ?>">Units</a></li>
            <li><a class="dropdown-item" href="<?= app_base_url('admin/backup_db.php') ?>">Backup Database</a></li>
            <?php endif; ?>
            <li><a class="dropdown-item" href="<?= app_base_url('admin/settings.php') ?>">Approvals</a></li>
          </ul>
        </li>
        <?php endif; ?>
        <?php endif; ?>
      </ul>
      <ul class="navbar-nav">
        <li class="nav-item me-2">
          <div class="btn-group btn-group-sm" role="group" aria-label="Language toggle">
            <a class="btn btn-outline-light" href="<?= app_base_url('toggle_lang.php?lang=hi') ?>">🇮🇳 हिंदी</a>
            <a class="btn btn-outline-light" href="<?= app_base_url('toggle_lang.php?lang=en') ?>">🇬🇧 English</a>
          </div>
        </li>
        <?php if ($user): ?>
        <li class="nav-item"><span class="navbar-text me-3">👤 <?= esc($user['name']) ?> (<?= esc($user['role']) ?>)</span></li>
        <li class="nav-item"><a class="nav-link" href="<?= app_base_url('logout.php') ?>">लॉगआउट</a></li>
        <?php else: ?>
        <li class="nav-item"><a class="nav-link" href="<?= app_base_url('login.php') ?>">लॉगिन</a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
  </nav>
  <main class="container my-4">
    <?php foreach ($flash as $f): ?>
      <div class="alert alert-<?= esc($f['type']) ?> alert-dismissible fade show" role="alert">
        <?= esc($f['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endforeach; ?>
