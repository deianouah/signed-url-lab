<?php
// Issues a temporary signed URL only to a signed-in, subscribed user.
require __DIR__ . '/lib.php';
apply_cors();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$user = current_user();
if ($user === null) {
    fail(401, 'login required');
}
if (empty($user['subscribed'])) {
    fail(403, 'subscription required');
}

$file = (string)($_GET['f'] ?? 'video.mp4');
$files = cfg('files');
if (!isset($files[$file])) {
    fail(404, 'file not found');
}

echo json_encode(make_signed_url($file, $user['id']), JSON_UNESCAPED_SLASHES);
