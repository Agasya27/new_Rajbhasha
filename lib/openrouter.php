<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

/**
 * openrouter_chat
 * Thin wrapper around OpenRouter's chat completions endpoint.
 *
 * @param array $messages [ ['role'=>'system|user|assistant','content'=>'...'], ... ]
 * @param array $options  ['model'=>string, 'timeout'=>int, 'extra_headers'=>['HTTP-Referer'=>..., 'X-Title'=>...], 'max_tokens'=>int]
 * @return array          ['ok'=>bool, 'reply'=>string|null, 'raw'=>array|null, 'error'=>string|null]
 */
function openrouter_chat(array $messages, array $options = []): array {
    $apiKey = OPENROUTER_API_KEY;
    if (!$apiKey) return ['ok'=>false,'reply'=>null,'raw'=>null,'error'=>'Missing OPENROUTER_API_KEY'];

    $baseUrl = rtrim(OPENROUTER_BASE_URL ?: 'https://openrouter.ai/api/v1', '/');
    $endpoint = $baseUrl . '/chat/completions';
    $model = (string)($options['model'] ?? OPENROUTER_MODEL ?? 'openai/gpt-4o');

    $payload = [
        'model' => $model,
        'messages' => array_values($messages),
    ];
    $max = isset($options['max_tokens']) ? (int)$options['max_tokens'] : (int)(defined('OPENROUTER_MAX_TOKENS') ? OPENROUTER_MAX_TOKENS : 512);
    if ($max > 0) { $payload['max_tokens'] = $max; }

    $headers = [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json',
    ];
    // Optional ranking metadata
    $referer = $options['extra_headers']['HTTP-Referer'] ?? OPENROUTER_SITE_URL;
    $title   = $options['extra_headers']['X-Title'] ?? OPENROUTER_SITE_TITLE;
    if (!empty($referer)) $headers[] = 'HTTP-Referer: ' . $referer;
    if (!empty($title))   $headers[] = 'X-Title: ' . $title;

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => (int)($options['timeout'] ?? 30),
    ]);

    $resp = curl_exec($ch);
    if ($resp === false) {
        $err = curl_error($ch);
        curl_close($ch);
        return ['ok'=>false,'reply'=>null,'raw'=>null,'error'=>'cURL error: '.$err];
    }
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    $json = json_decode($resp, true);
    if ($status >= 400) {
        $msg = $json['error']['message'] ?? ('HTTP '.$status);
        return ['ok'=>false,'reply'=>null,'raw'=>$json,'error'=>$msg];
    }

    $reply = $json['choices'][0]['message']['content'] ?? null;
    return ['ok'=>true,'reply'=>$reply,'raw'=>$json,'error'=>null];
}
