<?php
declare(strict_types=1);
require __DIR__ . '/../lib/openrouter.php';

$res = openrouter_chat([
    ['role' => 'user', 'content' => 'Say hi in one sentence.'],
], [
    'max_tokens' => 64,
]);

if (!$res['ok']) {
    fwrite(STDERR, 'ERR: ' . ($res['error'] ?? 'Unknown error') . PHP_EOL);
    exit(1);
}

echo $res['reply'], PHP_EOL;
