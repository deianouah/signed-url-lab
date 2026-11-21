<?php
// Simple public endpoint for testing CORS only (see cors-test.html)
require __DIR__ . '/lib.php';
apply_cors();

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => true, 'time' => date('c')]);
