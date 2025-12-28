<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

function esc(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function redirect(string $path): void {
    if (preg_match('#^https?://#', $path)) {
        header('Location: ' . $path);
    } else {
        header('Location: ' . app_base_url($path));
    }
    exit;
}

function is_post(): bool { return $_SERVER['REQUEST_METHOD'] === 'POST'; }

function json_response(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function flash_set(string $type, string $message): void {
    $_SESSION['flash'][] = ['type'=>$type,'message'=>$message];
}

function flash_get_all(): array {
    $msgs = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $msgs;
}
