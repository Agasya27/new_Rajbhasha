<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/csrf.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

$error = '';
if (is_post()) {
  verify_csrf();
  $email = $_POST['email'] ?? '';
  $password = $_POST['password'] ?? '';
  try {
    if (login($email, $password)) {
      redirect('dashboard.php');
    } else {
      $error = 'अमान्य प्रमाण-पत्र / Invalid credentials';
    }
  } catch (Throwable $e) {
    $error = 'Server error during login. Please verify database setup and try again.';
  }
}

include __DIR__ . '/../templates/header.php';
?>
<style>
<?php
  // Resolve login background image from public/assets/images
  // Priority: any file starting with "login_" or "login-"; else first image file
  $bgDataUrl = '';
  $bgFile = '';
  $bgMime = '';
  // Search both public/assets/images and project-root/assets/images
  $dirs = [
    __DIR__ . '/assets/images',                // public/assets/images
    dirname(__DIR__) . '/assets/images',       // ../assets/images (project root)
  ];
  $files = [];
  foreach ($dirs as $imgDir) {
    if (is_dir($imgDir)) {
      $found = glob($imgDir . '/*.{jpg,jpeg,png,webp}', GLOB_BRACE) ?: [];
      if ($found) { $files = array_merge($files, $found); }
    }
  }
  // Prefer login_* files
  $loginPrefer = array_values(array_filter($files, function($p){ $b = basename($p); return preg_match('/^login[_-]/i', $b); }));
  $choice = $loginPrefer[0] ?? ($files[0] ?? '');
  if ($choice && is_file($choice)) {
    $bgFile = $choice;
    $ext = strtolower(pathinfo($bgFile, PATHINFO_EXTENSION));
    $bgMime = $ext === 'png' ? 'image/png' : ($ext === 'webp' ? 'image/webp' : 'image/jpeg');
    // Try embed as data URI to avoid extra request
    $data = @file_get_contents($bgFile);
    if ($data !== false) { $bgDataUrl = 'data:' . $bgMime . ';base64,' . base64_encode($data); }
  }
?>
  body.login-bg {
    background:
      linear-gradient(180deg, rgba(0,0,0,0.35), rgba(0,0,0,0.35)),
      url('<?= $bgDataUrl ?: app_base_url('assets/images/'.($choice ? basename($choice) : 'login_bg.jpg')) ?>');
    background-position: center center;
    background-size: cover;
    background-repeat: no-repeat;
    background-attachment: fixed;
  }
  .login-wrapper { min-height: calc(100vh - 2rem); }
  .login-card {
    backdrop-filter: blur(8px);
    background: rgba(255,255,255,0.92);
    border: 1px solid rgba(255,255,255,0.6);
    box-shadow: 0 10px 30px rgba(0,0,0,0.25);
  }
  .login-logo {
    display:flex; align-items:center; gap:12px; margin-bottom:10px;
  }
  .login-logo img { width:48px; height:48px; }
  .login-logo span { font-weight:700; letter-spacing: .2px; }
</style>
<script>
  document.addEventListener('DOMContentLoaded', ()=>{ document.body.classList.add('login-bg'); });
</script>
<div class="login-wrapper d-flex align-items-center justify-content-center">
  <div class="row justify-content-center w-100 px-2">
  <div class="col-md-5">
    <div class="card shadow-sm login-card">
      <div class="card-body">
        <div class="login-logo">
<?php
  // Resolve a logo image near title: prefer explicit logo.* then brand*/login* then fallback
  $loginLogoDataUrl = '';
  try {
    $dirs = [ dirname(__DIR__) . '/assets/images', __DIR__ . '/assets/images' ];
    $exts = ['png','jpg','jpeg','webp'];
    $picked = '';
    foreach ($dirs as $d) {
      foreach ($exts as $e) { $f = $d.'/logo.'.$e; if (is_file($f)) { $picked = $f; break 2; } }
    }
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
    if ($picked && is_file($picked)) {
      $ext = strtolower(pathinfo($picked, PATHINFO_EXTENSION));
      $mime = $ext === 'png' ? 'image/png' : ($ext === 'webp' ? 'image/webp' : 'image/jpeg');
      $data = @file_get_contents($picked);
      if ($data !== false) { $loginLogoDataUrl = 'data:'.$mime.';base64,'.base64_encode($data); }
    }
  } catch (Throwable $e) { /* ignore */ }
?>
          <img src="<?= $loginLogoDataUrl ?: app_base_url('assets/images/wcl_logo.png') ?>" alt="WCL"/>
          <span>WCL Rajbhasha Portal</span>
        </div>
        <h5 class="card-title mb-3">लॉगिन / Login</h5>
        <?php if ($error): ?><div class="alert alert-danger"><?= esc($error) ?></div><?php endif; ?>
        <form method="post" autocomplete="off">
          <?= csrf_input() ?>
          <div class="mb-3">
            <label for="email" class="form-label">ईमेल / Email</label>
            <input type="email" class="form-control" id="email" name="email" required>
            <div class="form-hint">उदा. admin@example.com</div>
          </div>
          <div class="mb-3">
            <label for="password" class="form-label">पासवर्ड / Password</label>
            <input type="password" class="form-control" id="password" name="password" required>
          </div>
          <button type="submit" class="btn btn-primary w-100">प्रवेश करें / Sign in</button>
        </form>
        <hr>
        <p class="mb-0 small">डिफ़ॉल्ट उपयोगकर्ता / Default Users:</p>
        <ul class="small mb-0">
          <li>Super Admin: admin@example.com / Admin@123</li>
          <li>Officer: officer@example.com / Officer@123</li>
        </ul>
      </div>
    </div>
  </div>
  </div>
</div>
<?php include __DIR__ . '/../templates/footer.php'; ?>
