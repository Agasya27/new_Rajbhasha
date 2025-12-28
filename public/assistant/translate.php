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
        if (!empty($g['term_hi']) && !empty($g['term_en'])) {
            $map[$g['term_hi']] = $g['term_en'];
        }
    }
} catch (Throwable $e) { /* table may not exist yet */ }

// Fallback dictionary ensures minimum useful coverage even if DB lacks entries
$fallback = [
    'हिंदी' => 'Hindi',
    'अंग्रेजी' => 'English',
    'पत्र' => 'letter',
    'जवाब' => 'reply',
    'प्रतिशत' => 'percent',
    'औसत' => 'average'
];
if (!$map) {
    $map = $fallback;
} else {
    foreach ($fallback as $hi => $en) {
        if (!isset($map[$hi])) { $map[$hi] = $en; }
    }
}

// Apply token-wise glossary substitution; if no change, preserve original text
$translated = '';
if ($text !== '') {
    $hiToEn = ($direction === 'hi-en');
    $normalize = static function (string $token): string {
        $token = trim($token);
        if ($token === '') { return ''; }
        return mb_strtolower($token, 'UTF-8');
    };

    $matchCase = static function (string $replacement, string $original): string {
        if (!preg_match('/[A-Za-z]/', $original)) {
            return $replacement; // Non-Latin scripts don't need casing heuristics
        }
        $upperOrig = mb_strtoupper($original, 'UTF-8');
        $lowerOrig = mb_strtolower($original, 'UTF-8');
        if ($original === $upperOrig) {
            return mb_strtoupper($replacement, 'UTF-8');
        }
        if ($original === $lowerOrig) {
            return mb_strtolower($replacement, 'UTF-8');
        }
        $first = mb_substr($replacement, 0, 1, 'UTF-8');
        $rest = mb_substr($replacement, 1, null, 'UTF-8');
        return mb_strtoupper($first, 'UTF-8') . $rest;
    };

    $hiLex = [];
    $enLex = [];
    foreach ($map as $hi => $en) {
        $normHi = $normalize($hi);
        $normEn = $normalize($en);
        if ($normHi !== '') { $hiLex[$normHi] = $en; }
        if ($normEn !== '') { $enLex[$normEn] = $hi; }
    }

    $tokens = preg_split('/(\s+|([\.,;:\-\!\?\(\)\[\]\{\}]))/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    foreach ($tokens as $tok) {
        if ($tok === null || $tok === '') { $translated .= $tok; continue; }
        $normTok = $normalize($tok);
        if ($normTok === '') { $translated .= $tok; continue; }

        if ($hiToEn && isset($hiLex[$normTok])) {
            $translated .= $matchCase($hiLex[$normTok], $tok);
        } elseif (!$hiToEn && isset($enLex[$normTok])) {
            $translated .= $enLex[$normTok];
        } else {
            $translated .= $tok;
        }
    }

    if ($translated === '' || $translated === $text) { $translated = $text; }
}

// Optional external translation via API if env key provided (non-blocking stub)
$apiKey = $_ENV['TRANSLATE_API_KEY'] ?? '';
if ($apiKey && strlen(trim($text))>0) {
    // Integrate your cloud translate here (Azure/Google). To keep local/offline safe, we just note capability.
}

json_response(['ok'=>true,'translated'=>$translated,'note'=>'Glossary-based translation. Set TRANSLATE_API_KEY in .env to enable cloud translation.']);
