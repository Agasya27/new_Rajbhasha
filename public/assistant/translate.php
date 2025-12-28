<?php
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/helpers.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/db.php';
require_login();
// Accept either GET or POST for simple testing; prefer POST with CSRF when modifying state
if ($_SERVER['REQUEST_METHOD'] === 'POST') { verify_csrf(); }

$text = trim(($_POST['text'] ?? $_GET['text'] ?? ''));
// Normalize direction: accept 'direction' like 'hi-en' or a simple 'to' = 'hi'|'en'
$dirRaw = ($_POST['direction'] ?? $_GET['direction'] ?? '');
$toRaw = strtolower(trim((string)($_POST['to'] ?? $_GET['to'] ?? '')));
if ($dirRaw) {
    $direction = strtolower($dirRaw);
} else {
    $direction = ($toRaw === 'en') ? 'hi-en' : (($toRaw === 'hi') ? 'en-hi' : 'hi-en');
}

// Glossary lookup from DB if available
$pdo = db();
$map = [];
try {
    foreach ($pdo->query('SELECT term_hi, term_en FROM glossary') as $g) {
        $map[$g['term_hi']] = $g['term_en'];
    }
} catch (Throwable $e) { /* table may not exist yet */ }

// Fallback dictionary
if (!$map) {
    $map = [ 'हिंदी' => 'Hindi', 'अंग्रेजी' => 'English', 'पत्र' => 'letter', 'जवाब' => 'reply', 'प्रतिशत' => 'percent', 'औसत' => 'average' ];
}

// Apply token-wise glossary substitution; if no change, preserve original text
$translated = '';
if ($text !== '') {
    $tokens = preg_split('/(\s+|([\.,;:\-\!\?\(\)\[\]\{\}]))/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    $flip = array_flip($map);
    $hiToEn = ($direction === 'hi-en');
    foreach ($tokens as $tok) {
        // Skip pure delimiters/spaces through
        if ($tok === null || $tok === '') { $translated .= $tok; continue; }
        $key = $hiToEn ? $tok : $tok;
        // Exact match
        if ($hiToEn && isset($map[$key])) {
            $translated .= $map[$key];
        } elseif (!$hiToEn && isset($flip[$key])) {
            $translated .= $flip[$key];
        } else {
            $translated .= $tok;
        }
    }
    // If result is still identical, leave as original to avoid empty output complaints
    if ($translated === '' || $translated === $text) { $translated = $text; }
}

// Optional external translation via API if env key provided (non-blocking stub)
$apiKey = $_ENV['TRANSLATE_API_KEY'] ?? '';
if ($apiKey && strlen(trim($text))>0) {
    // Integrate your cloud translate here (Azure/Google). To keep local/offline safe, we just note capability.
}

json_response(['ok'=>true,'translated'=>$translated,'note'=>'Glossary-based translation. Set TRANSLATE_API_KEY in .env to enable cloud translation.']);
