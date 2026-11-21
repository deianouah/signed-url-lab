<?php
declare(strict_types=1);

function cfg(string $key)
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/config.php';
    }
    return $config[$key];
}

function start_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
        session_start();
    }
}

// Returns the current user with the latest subscription state from configuration (unsubscribe takes effect immediately).
function current_user(): ?array
{
    start_session();
    $id = $_SESSION['user'] ?? null;
    $users = cfg('users');
    if ($id === null || !isset($users[$id])) {
        return null;
    }
    return ['id' => $id] + $users[$id];
}

function fail(int $code, string $message): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

// CORS headers: sent only when the origin is in the allow-list.
function apply_cors(): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '' && in_array($origin, cfg('allowed_origins'), true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
        header('Access-Control-Allow-Methods: GET, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Range');
        header('Access-Control-Expose-Headers: Content-Range, Accept-Ranges, Content-Length');
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '';
}

function token_sig(string $file, string $uid, int $exp, string $ip): string
{
    return hash_hmac('sha256', implode('|', [$file, $uid, (string)$exp, $ip]), cfg('secret'));
}

function base_url(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    return $scheme . '://' . $_SERVER['HTTP_HOST'] . $dir;
}

function make_signed_url(string $file, string $uid): array
{
    $ttl = (int)cfg('ttl');
    $exp = time() + $ttl;
    $ip = cfg('bind_ip') ? client_ip() : '';
    $query = http_build_query([
        'f'   => $file,
        'u'   => $uid,
        'exp' => $exp,
        'sig' => token_sig($file, $uid, $exp, $ip),
    ]);
    return ['url' => base_url() . '/media.php?' . $query, 'exp' => $exp, 'expires_in' => $ttl];
}
