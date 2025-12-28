<?php
require_once __DIR__ . '/../lib/hindi_normalizer.php';

$cases = [
    'खाना' => 'काना',
    'घरेलू' => 'गरेलू',
    'भाषा' => 'बासा',
    'फ़सलों' => 'फ़सलों', // foreign f with nukta stays untouched
    'कर्मचारी' => 'कर्मचारी', // unchanged when no mapping needed
];

$failures = 0;
foreach ($cases as $input => $expected) {
    $actual = normalize_hindi_phonetics($input);
    $status = $actual === $expected ? 'PASS' : 'FAIL';
    printf("[%s] %s → %s (expected %s)\n", $status, $input, $actual, $expected);
    if ($status === 'FAIL') {
        $failures++;
    }
}

exit($failures === 0 ? 0 : 1);
