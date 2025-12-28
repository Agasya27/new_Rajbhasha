<?php
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/helpers.php';
require_once __DIR__ . '/../../lib/csrf.php';
// Load OpenRouter helper if available
if (!function_exists('openrouter_chat')) {
  $or = __DIR__ . '/../../lib/openrouter.php';
  if (is_file($or)) { require_once $or; }
}
if (!function_exists('normalize_hindi_phonetics')) {
  $norm = __DIR__ . '/../../lib/hindi_normalizer.php';
  if (is_file($norm)) { require_once $norm; }
}

// Require login to prevent exposing your key to anonymous traffic
if (!is_logged_in()) {
  json_response(['status'=>'error','message'=>'Unauthorized. Please log in.'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo 'Method Not Allowed';
  exit;
}

verify_csrf();

// Ensure JSON content type for all responses
header('Content-Type: application/json; charset=UTF-8');

try {
  $prompt = trim((string)($_POST['prompt'] ?? ''));
  if ($prompt === '') {
    json_response(['status'=>'error','message'=>'Empty prompt'], 400);
  }

// Simple per-session memory
if (!isset($_SESSION['assistant_chat']) || !is_array($_SESSION['assistant_chat'])) {
  $_SESSION['assistant_chat'] = [];
}

// Load knowledge context (RAG-lite)
$context = '';
if (ASSISTANT_KNOWLEDGE_ENABLED) {
  try {
    $files = [];
    $dir = ASSISTANT_KNOWLEDGE_DIR;
    if (is_dir($dir)) {
      foreach (glob($dir.'/*.md') ?: [] as $f) { $files[] = $f; }
    }
    $readme = BASE_PATH . '/README.md';
    if (is_file($readme)) { $files[] = $readme; }
    $needle = mb_strtolower($prompt);
    $needleNorm = function_exists('normalize_hindi_phonetics') ? normalize_hindi_phonetics($needle) : $needle;
    $promptTokens = array_filter(preg_split('/\s+/', $needle));
    $promptTokensNorm = ($needleNorm !== $needle) ? array_filter(preg_split('/\s+/', $needleNorm)) : [];
    $promptWords = array_unique(array_merge($promptTokens, $promptTokensNorm));
    $ranked = [];
    foreach ($files as $f) {
      $txt = @file_get_contents($f);
      if ($txt === false) continue;
      $lc = mb_strtolower($txt);
      $lcNorm = function_exists('normalize_hindi_phonetics') ? normalize_hindi_phonetics($lc) : $lc;
      // naive score: count of prompt words present
      $score = 0;
      foreach ($promptWords as $w) {
        if ($w === '') continue;
        if (mb_strpos($lc, $w) !== false) {
          $score++;
          continue;
        }
        if ($lcNorm !== $lc && mb_strpos($lcNorm, $w) !== false) {
          $score++;
        }
      }
      $ranked[] = ['file'=>$f,'score'=>$score,'text'=>$txt];
    }
    usort($ranked, function($a,$b){ return $b['score'] <=> $a['score']; });
    $budget = max(1000, ASSISTANT_CONTEXT_CHARS);
    $collected = '';
    $used = 0;
    foreach (array_slice($ranked, 0, 3) as $r) {
      if ($used >= $budget) break;
      $chunk = mb_substr($r['text'], 0, min(2000, $budget - $used));
      $collected .= "\n\n# " . basename($r['file']) . "\n" . $chunk;
      $used += mb_strlen($chunk);
    }
    if ($collected) {
      $context = "You are assisting with the WCL Rajbhasha Portal. Use the following project context to answer precisely. If something is unclear or missing, ask clarifying questions before assuming.\n" . $collected;
    }
  } catch (Throwable $e) { /* ignore context errors */ }
}

// Build message history with system + optional context
$messages = [];
$messages[] = ['role'=>'system','content'=>
  "You are the in-app assistant for WCL Rajbhasha Portal. Be concise, bilingual (Hindi + English where helpful), and provide step-by-step, actionable guidance that references exact page names, menu paths, or form field labels. Never reveal secrets or API keys. If the user asks to perform an action in the portal, outline the steps and any validation rules."
];
if ($context) { $messages[] = ['role'=>'system','content'=>$context]; }

// Append last N turns from session memory
$history = $_SESSION['assistant_chat'];
if (!empty($history)) {
  $tail = array_slice($history, -ASSISTANT_MAX_HISTORY);
  foreach ($tail as $m) { $messages[] = $m; }
}

// Current user input
$messages[] = ['role'=>'user','content'=>$prompt];

  if (!function_exists('openrouter_chat')) {
    json_response(['status'=>'error','message'=>'Assistant backend missing. Please restore lib/openrouter.php'], 500);
  }

  $res = openrouter_chat($messages, [ 'max_tokens' => OPENROUTER_MAX_TOKENS ]);
  if (!$res['ok']) {
    json_response(['status'=>'error','message'=>$res['error'] ?? 'LLM error'], 500);
  }
  // Save to memory
  $_SESSION['assistant_chat'][] = ['role'=>'user','content'=>$prompt];
  $_SESSION['assistant_chat'][] = ['role'=>'assistant','content'=>$res['reply'] ?? ''];

  json_response(['status'=>'ok','reply'=>$res['reply']]);
} catch (Throwable $e) {
  json_response(['status'=>'error','message'=>'Server exception: '.$e->getMessage()], 500);
}
