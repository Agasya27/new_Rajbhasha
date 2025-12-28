<?php
declare(strict_types=1);
require __DIR__ . '/../lib/openrouter.php';

// Call without options to verify default OPENROUTER_MAX_TOKENS is applied
$res = openrouter_chat([
    ['role' => 'user', 'content' => 'Reply briefly with one sentence.'],
]);

if (!$res['ok']) {
    fwrite(STDERR, 'ERR: ' . ($res['error'] ?? 'Unknown error') . PHP_EOL);
    exit(1);
}

echo $res['reply'], PHP_EOL;
