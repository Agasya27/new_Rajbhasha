<?php
// Quick local OCR test without web auth/CSRF.
// Usage: php scripts/test_ocr_local.php <file> [lang]

require_once __DIR__ . '/../lib/config.php';

if ($argc < 2) {
    fwrite(STDERR, "Usage: php scripts/test_ocr_local.php <file> [lang]\n");
    exit(1);
}
$path = $argv[1];
$lang = $argv[2] ?? 'hin+eng';
if (!is_file($path)) {
    fwrite(STDERR, "File not found: $path\n");
    exit(1);
}

function find_exe_local(string $exe): ?string {
    $out = trim((string)@shell_exec('where '.escapeshellarg($exe).' 2> NUL'));
    if (!$out) { $out = trim((string)@shell_exec('which '.escapeshellarg($exe).' 2>/dev/null')); }
    $candidates = [];
    if ($out) {
        foreach (preg_split('/\r?\n/', $out) as $line) { $line = trim($line); if ($line !== '') $candidates[] = $line; }
    }
    $pf = getenv('ProgramFiles') ?: 'C:\\Program Files';
    $pf86 = getenv('ProgramFiles(x86)') ?: 'C:\\Program Files (x86)';
    $winPaths = [
        $pf.'\\Tesseract-OCR\\tesseract.exe',
        $pf86.'\\Tesseract-OCR\\tesseract.exe',
        $pf.'\\ImageMagick-7.1.2-Q16-HDRI\\magick.exe',
        $pf.'\\ImageMagick-7.1.1-Q16-HDRI\\magick.exe',
        $pf.'\\ImageMagick-7.1.1-Q16\\magick.exe',
        $pf.'\\poppler\\bin\\pdftotext.exe',
        $pf.'\\poppler-0.68.0\\bin\\pdftotext.exe',
        $pf.'\\poppler-23.11.0\\Library\\bin\\pdftotext.exe',
        'C:\\Program Files\\Git\\mingw64\\bin\\pdftotext.exe',
    ];
    foreach ($winPaths as $p) { if (is_file($p)) $candidates[] = $p; }
    foreach ($candidates as $p) { if (is_file($p)) return $p; }
    return null;
}
function get_tool_path_local(string $tool): ?string {
    if ($tool === 'tesseract' && defined('OCR_TESSERACT_EXE') && OCR_TESSERACT_EXE) return OCR_TESSERACT_EXE;
    if ($tool === 'pdftotext' && defined('OCR_PDFTOTEXT_EXE') && OCR_PDFTOTEXT_EXE) return OCR_PDFTOTEXT_EXE;
    if ($tool === 'magick' && defined('OCR_MAGICK_EXE') && OCR_MAGICK_EXE) return OCR_MAGICK_EXE;
    return find_exe_local($tool);
}

$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$allowed = ['png','jpg','jpeg','tif','tiff','pdf'];
if (!in_array($ext, $allowed, true)) {
    fwrite(STDERR, "Unsupported file type: .$ext\n");
    exit(1);
}

$text = '';
$method = '';

if ($ext === 'pdf') {
    $pdftotext = get_tool_path_local('pdftotext');
        if ($pdftotext) {
            $cmd = '"'.$pdftotext.'" -layout -enc UTF-8 ' . escapeshellarg($path) . ' -';
            $out = @shell_exec($cmd);
            if (is_string($out) && preg_match('/[A-Za-z\x{0900}-\x{097F}]/u', $out)) { $text = $out; $method = 'pdftotext'; }
    }
    if ($text === '') {
        $tesseract = get_tool_path_local('tesseract');
        $magick = get_tool_path_local('magick');
        if ($tesseract && defined('OCR_TESSDATA_PREFIX') && OCR_TESSDATA_PREFIX) { @putenv('TESSDATA_PREFIX='.OCR_TESSDATA_PREFIX); }
        if ($tesseract && $magick) {
            $tmpBase = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ocr_pdf_' . uniqid();
            $max = max(1, (int)OCR_MAX_PAGES);
            $range = '[0-'.($max-1).']';
            $renderCmd = '"'.$magick.'" -density '.(int)OCR_DENSITY_DPI.' ' . escapeshellarg($path) . $range . ' -colorspace sRGB -alpha off ' . escapeshellarg($tmpBase.'_%02d.png');
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
    $tesseract = get_tool_path_local('tesseract');
    if ($tesseract && defined('OCR_TESSDATA_PREFIX') && OCR_TESSDATA_PREFIX) { @putenv('TESSDATA_PREFIX='.OCR_TESSDATA_PREFIX); }
    if ($tesseract) {
        $outBase = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ocr_' . uniqid();
        $cmd = '"' . $tesseract . '" ' . escapeshellarg($path) . ' ' . escapeshellarg($outBase) . ' -l ' . escapeshellarg($lang);
        @shell_exec($cmd);
        $outTxt = $outBase . '.txt';
        if (is_file($outTxt)) { $text = file_get_contents($outTxt); @unlink($outTxt); $method = 'tesseract'; }
    }
}

if ($text === '') {
    fwrite(STDERR, "OCR failed. Ensure tools are installed and paths configured.\n");
    exit(2);
}

$chars = mb_strlen($text, 'UTF-8');
$lines = substr_count($text, "\n") + 1;
$words = preg_split('/\s+/u', trim($text));
$wordCount = $words && $words[0] !== '' ? count($words) : 0;

$result = [
    'ok'=>true,
    'stats'=>['chars'=>$chars,'lines'=>$lines,'words'=>$wordCount],
    'meta'=>['method'=>$method,'lang'=>$lang],
];

echo json_encode($result, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT) . "\n";
// Print a small preview of the text
$preview = trim(mb_substr($text, 0, 300));
if ($preview !== '') {
    echo "--- Preview ---\n";
    echo $preview . (mb_strlen($text,'UTF-8')>300?"...\n":"\n");
}
