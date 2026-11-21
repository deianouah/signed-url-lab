<?php
require __DIR__ . '/lib.php';
start_session();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $users = cfg('users');
    $name = (string)($_POST['username'] ?? '');
    $pass = (string)($_POST['password'] ?? '');

    if (isset($users[$name]) && hash_equals($users[$name]['password'], $pass)) {
        session_regenerate_id(true);
        $_SESSION['user'] = $name;
        header('Location: index.php');
        exit;
    }
    header('Location: index.php?err=1');
    exit;
}

header('Location: index.php');
