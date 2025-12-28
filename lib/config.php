<?php
// Configuration and environment loader
declare(strict_types=1);

// Base path
define('BASE_PATH', realpath(__DIR__ . '/..'));

// Attempt to load Composer autoloader (for Dompdf and phpdotenv)
if (file_exists(BASE_PATH . '/vendor/autoload.php')) {
    require_once BASE_PATH . '/vendor/autoload.php';
}

// Load .env if present
if (class_exists('Dotenv\\Dotenv') && file_exists(BASE_PATH . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(BASE_PATH);
    $dotenv->safeLoad();
}

// Defaults for XAMPP
$DB_HOST = $_ENV['DB_HOST'] ?? 'localhost';
$DB_USER = $_ENV['DB_USER'] ?? 'root';
$DB_PASS = $_ENV['DB_PASS'] ?? '';
$DB_NAME = $_ENV['DB_NAME'] ?? 'rajbhasha_db';

// Determine BASE_URL: prefer .env, else auto-detect from request (handles /newrajbhasha/ path)
if (!empty($_ENV['BASE_URL'])) {
    $BASE_URL = rtrim($_ENV['BASE_URL'], '/') . '/';
} else {
    // Robust base URL detection: anchor to the application's public root
    // irrespective of the current PHP file subdirectory (e.g., /report, /assistant, /admin)
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/');
    // If script path contains a known public subfolder, trim to that root
    $basePathUri = preg_replace('#/(report|assistant|admin|uploads|assets)(/.*)?$#', '/', $script);
    if ($basePathUri === null || $basePathUri === '') { $basePathUri = '/'; }
    // Ensure trailing slash
    if (substr($basePathUri, -1) !== '/') { $basePathUri .= '/'; }
    $BASE_URL = $scheme . '://' . $host . $basePathUri;
}

// Expose via constants and a function
define('APP_DB_HOST', $DB_HOST);
define('APP_DB_USER', $DB_USER);
define('APP_DB_PASS', $DB_PASS);
define('APP_DB_NAME', $DB_NAME);
define('APP_BASE_URL', $BASE_URL);

// OpenRouter configuration
define('OPENROUTER_API_KEY', $_ENV['OPENROUTER_API_KEY'] ?? '');
define('OPENROUTER_BASE_URL', $_ENV['OPENROUTER_BASE_URL'] ?? 'https://openrouter.ai/api/v1');
define('OPENROUTER_MODEL', $_ENV['OPENROUTER_MODEL'] ?? 'openai/gpt-4o');
define('OPENROUTER_SITE_URL', $_ENV['OPENROUTER_SITE_URL'] ?? '');
define('OPENROUTER_SITE_TITLE', $_ENV['OPENROUTER_SITE_TITLE'] ?? '');
define('OPENROUTER_MAX_TOKENS', (int)($_ENV['OPENROUTER_MAX_TOKENS'] ?? 512));

// Assistant knowledge + memory defaults
define('ASSISTANT_KNOWLEDGE_ENABLED', isset($_ENV['ASSISTANT_KNOWLEDGE_ENABLED']) ? filter_var($_ENV['ASSISTANT_KNOWLEDGE_ENABLED'], FILTER_VALIDATE_BOOL) : true);
define('ASSISTANT_KNOWLEDGE_DIR', $_ENV['ASSISTANT_KNOWLEDGE_DIR'] ?? (BASE_PATH . '/knowledge'));
define('ASSISTANT_CONTEXT_CHARS', (int)($_ENV['ASSISTANT_CONTEXT_CHARS'] ?? 6000));
define('ASSISTANT_MAX_HISTORY', (int)($_ENV['ASSISTANT_MAX_HISTORY'] ?? 6));

// OCR settings
define('OCR_MAX_PAGES', (int)($_ENV['OCR_MAX_PAGES'] ?? 5));
define('OCR_DENSITY_DPI', (int)($_ENV['OCR_DENSITY_DPI'] ?? 300));
define('OCR_TESSERACT_EXE', $_ENV['OCR_TESSERACT_EXE'] ?? '');
define('OCR_PDFTOTEXT_EXE', $_ENV['OCR_PDFTOTEXT_EXE'] ?? '');
define('OCR_MAGICK_EXE', $_ENV['OCR_MAGICK_EXE'] ?? '');
define('OCR_TESSDATA_PREFIX', $_ENV['OCR_TESSDATA_PREFIX'] ?? '');

// Uploads directory
define('UPLOAD_DIR', BASE_PATH . '/public/uploads');
if (!is_dir(UPLOAD_DIR)) {
    @mkdir(UPLOAD_DIR, 0775, true);
}

// Force UTF-8 for mb and output
mb_internal_encoding('UTF-8');
header_remove('X-Powered-By');

function app_base_url(string $path = ''): string {
    return APP_BASE_URL . ltrim($path, '/');
}
