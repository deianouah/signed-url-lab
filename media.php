<?php
// Serves the protected file only when the link is signed and valid and the user remains subscribed.
// Supports range requests so seeking works in video players.
// It intentionally does not use the session so streaming does not lock the session file.
require __DIR__ . '/lib.php';
apply_cors();

$file = basename((string)($_GET['f'] ?? ''));
$uid  = (string)($_GET['u'] ?? '');
$exp  = (int)($_GET['exp'] ?? 0);
$sig  = (string)($_GET['sig'] ?? '');

$files = cfg('files');
$users = cfg('users');

if (!isset($files[$file])) {
    fail(404, 'file not found');
}
if ($uid === '' || $exp === 0 || $sig === '') {
    fail(403, 'missing token');
}

$ip = cfg('bind_ip') ? client_ip() : '';
if (!hash_equals(token_sig($file, $uid, $exp, $ip), $sig)) {
    fail(403, 'invalid signature');
}
if ($exp < time()) {
    fail(403, 'link expired');
}
if (empty($users[$uid]['subscribed'])) {
    fail(403, 'subscription inactive');
}

$path = __DIR__ . '/private/' . $file;
if (!is_file($path)) {
    fail(404, 'file missing on disk');
}

$size  = filesize($path);
$start = 0;
$end   = $size - 1;

header('Content-Type: ' . $files[$file]);
header('Accept-Ranges: bytes');
header('Cache-Control: private, no-store');

$range = $_SERVER['HTTP_RANGE'] ?? '';
if ($range !== '' && preg_match('/^bytes=(\d*)-(\d*)$/', $range, $m) && ($m[1] !== '' || $m[2] !== '')) {
    if ($m[1] === '') {
        // bytes=-N pattern: last N bytes
        $start = max(0, $size - (int)$m[2]);
    } else {
        $start = (int)$m[1];
        if ($m[2] !== '') {
            $end = min((int)$m[2], $size - 1);
        }
    }
    if ($start >= $size || $start > $end) {
        header('Content-Range: bytes */' . $size);
        http_response_code(416);
        exit;
    }
    http_response_code(206);
    header("Content-Range: bytes $start-$end/$size");
}

$length = $end - $start + 1;
header('Content-Length: ' . $length);

set_time_limit(0);
while (ob_get_level() > 0) {
    ob_end_clean();
}

$fh = fopen($path, 'rb');
fseek($fh, $start);
$left = $length;
while ($left > 0 && !feof($fh)) {
    $chunk = fread($fh, min(65536, $left));
    if ($chunk === false) {
        break;
    }
    echo $chunk;
    $left -= strlen($chunk);
    flush();
}
fclose($fh);
