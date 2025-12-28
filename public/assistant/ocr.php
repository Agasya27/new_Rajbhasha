<?php
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/helpers.php';
require_once __DIR__ . '/../../lib/csrf.php';
if (!is_logged_in()) {
    json_response(['ok'=>false,'error'=>'Unauthorized. Please log in.'], 401);
}
verify_csrf();

// Accept uploaded file or a path
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    json_response(['ok'=>false,'error'=>'कोई फ़ाइल प्राप्त नहीं हुई / No file uploaded'], 400);
}

$tmp = $_FILES['file']['tmp_name'];
$name = basename($_FILES['file']['name']);
$ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
$allowed = ['png','jpg','jpeg','tif','tiff','pdf'];
if (!in_array($ext, $allowed, true)) {
    json_response(['ok'=>false,'error'=>'समर्थित फ़ाइल प्रकार नहीं / Unsupported file type'], 400);
}
// Limit size (15MB)
if (filesize($tmp) > 15 * 1024 * 1024) {
    json_response(['ok'=>false,'error'=>'फ़ाइल बहुत बड़ी है (15MB सीमा) / File too large (15MB limit)'], 400);
}

// Utility to locate executables cross-platform
function find_exe(string $exe): ?string {
    // 1) PATH lookup
    $out = trim((string)@shell_exec('where '.escapeshellarg($exe).' 2> NUL'));
    if (!$out) { $out = trim((string)@shell_exec('which '.escapeshellarg($exe).' 2>/dev/null')); }
    $candidates = [];
    if ($out) {
        foreach (preg_split('/\r?\n/', $out) as $line) { $line = trim($line); if ($line !== '') $candidates[] = $line; }
    }
    // 2) Common Windows install paths
    $pf = getenv('ProgramFiles') ?: 'C:\\Program Files';
    $pf86 = getenv('ProgramFiles(x86)') ?: 'C:\\Program Files (x86)';
    $winPaths = [
        $pf.'\\Tesseract-OCR\\tesseract.exe',
        $pf86.'\\Tesseract-OCR\\tesseract.exe',
        $pf.'\\ImageMagick-7.1.1-Q16-HDRI\\magick.exe',
        $pf.'\\ImageMagick-7.1.1-Q16\\magick.exe',
        $pf86.'\\ImageMagick-7.1.1-Q16-HDRI\\magick.exe',
        $pf.'\\poppler\\bin\\pdftotext.exe',
        $pf.'\\poppler-0.68.0\\bin\\pdftotext.exe',
        $pf.'\\poppler-23.11.0\\Library\\bin\\pdftotext.exe',
    ];
    foreach ($winPaths as $p) { if (is_file($p)) $candidates[] = $p; }
    // Return the first existing candidate
    foreach ($candidates as $p) { if (is_file($p)) return $p; }
    return null;
}

function get_tool_path(string $tool): ?string {
    // Allow explicit overrides via .env
    if ($tool === 'tesseract' && defined('OCR_TESSERACT_EXE') && OCR_TESSERACT_EXE) return OCR_TESSERACT_EXE;
    if ($tool === 'pdftotext' && defined('OCR_PDFTOTEXT_EXE') && OCR_PDFTOTEXT_EXE) return OCR_PDFTOTEXT_EXE;
    if ($tool === 'magick' && defined('OCR_MAGICK_EXE') && OCR_MAGICK_EXE) return OCR_MAGICK_EXE;
    return find_exe($tool);
}

// Prefer language hint (default hin+eng)
$lang = isset($_POST['lang']) && preg_match('/^[a-z\+]+$/i', $_POST['lang']) ? $_POST['lang'] : 'hin+eng';

$text = '';
$method = '';

if ($ext === 'pdf') {
    // 1) Try pdftotext for digital PDFs
    $pdftotext = get_tool_path('pdftotext');
    if ($pdftotext) {
        // -layout preserves spatial layout; output to stdout
        $cmd = '"'.$pdftotext.'" -layout -enc UTF-8 ' . escapeshellarg($tmp) . ' -';
        $out = @shell_exec($cmd);
        // Require at least one Latin or Devanagari character to accept pdftotext result
        if (is_string($out) && preg_match('/[A-Za-z\x{0900}-\x{097F}]/u', $out)) {
            $text = $out;
            $method = 'pdftotext';
        }
    }
    // 2) If still empty, try tesseract on first page rendered via Ghostscript/ImageMagick (if available)
    if ($text === '') {
        $tesseract = get_tool_path('tesseract');
        $magick = get_tool_path('magick'); // ImageMagick v7 CLI
        // Ensure PATH includes user-provided binary directories so ImageMagick can find delegates like Ghostscript
        $binDirs = [];
        foreach (['OCR_TESSERACT_EXE', 'OCR_PDFTOTEXT_EXE', 'OCR_MAGICK_EXE', 'OCR_GS_EXE'] as $const) {
            if (defined($const) && constant($const)) {
                $dir = dirname((string)constant($const));
                if ($dir && !in_array($dir, $binDirs, true)) {
                    $binDirs[] = $dir;
                }
            }
        }
        if ($binDirs) {
            $currentPath = getenv('PATH') ?: '';
            $prefix = implode(PATH_SEPARATOR, $binDirs);
            if ($currentPath === '' || strpos($currentPath, $prefix) !== 0) {
                @putenv('PATH=' . $prefix . PATH_SEPARATOR . $currentPath);
            }
        }
        if ($tesseract && defined('OCR_TESSDATA_PREFIX') && OCR_TESSDATA_PREFIX) { @putenv('TESSDATA_PREFIX='.OCR_TESSDATA_PREFIX); }
        if ($magick && defined('OCR_GS_EXE') && OCR_GS_EXE) {
            @putenv('GS_PROG=' . OCR_GS_EXE);
            @putenv('MAGICK_GS_COMMAND=' . OCR_GS_EXE);
        }
        if ($tesseract && $magick) {
            $tmpBase = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ocr_pdf_' . uniqid();
            $max = max(1, (int)OCR_MAX_PAGES);
            $range = '[0-'.($max-1).']';
            // Render first N pages at configured DPI to PNGs
            $renderCmd = '"'.$magick.'" -density '.(int)OCR_DENSITY_DPI.' ' . escapeshellarg($tmp) . $range . ' -colorspace sRGB -alpha off ' . escapeshellarg($tmpBase.'_%02d.png');
            @shell_exec($renderCmd);
            $pages = glob($tmpBase.'_*.png') ?: [];
            foreach ($pages as $pg) {
                $outBase = $pg . '_out';
                $cmd = '"'.$tesseract.'" ' . escapeshellarg($pg) . ' ' . escapeshellarg($outBase) . ' -l ' . escapeshellarg($lang);
                @shell_exec($cmd);
                $txt = $outBase . '.txt';
                if (is_file($txt)) { $text .= file_get_contents($txt) . "\n"; @unlink($txt); $method = 'magick+tesseract'; }
                @unlink($pg);
            }
        }
    }
} else {
    // Image inputs -> Tesseract directly
    $tesseract = get_tool_path('tesseract');
    if ($tesseract && defined('OCR_TESSDATA_PREFIX') && OCR_TESSDATA_PREFIX) { @putenv('TESSDATA_PREFIX='.OCR_TESSDATA_PREFIX); }
    if ($tesseract) {
        $outBase = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ocr_' . uniqid();
        $cmd = '"' . $tesseract . '" ' . escapeshellarg($tmp) . ' ' . escapeshellarg($outBase) . ' -l ' . escapeshellarg($lang);
        @shell_exec($cmd);
        $outTxt = $outBase . '.txt';
        if (is_file($outTxt)) {
            $text = file_get_contents($outTxt);
            @unlink($outTxt);
            $method = 'tesseract';
        }
    }
}

if ($text === '') {
    $hint = [];
    $hint[] = 'Windows: Install Tesseract OCR (add to PATH).';
    $hint[] = 'PDF support: Install Poppler (pdftotext) or ImageMagick + Ghostscript.';
    $hint[] = 'Languages: Ensure `eng.traineddata` and `hin.traineddata` are installed.';
    json_response(['ok'=>false,'error'=>"OCR tools not available.\n".implode("\n", $hint)], 500);
}

// Basic stats
$chars = mb_strlen($text, 'UTF-8');
$lines = substr_count($text, "\n") + 1;
// Word count heuristic: split on whitespace
$words = preg_split('/\s+/u', trim($text));
$wordCount = $words && $words[0] !== '' ? count($words) : 0;

json_response([
    'ok'=>true,
    'text'=>$text,
    'stats'=>['chars'=>$chars,'lines'=>$lines,'words'=>$wordCount],
    'meta'=>['method'=>$method, 'lang'=>$lang]
]);
