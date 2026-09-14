<?php

// Docker: src/ is sibling of public/ → ROOT_DIR = parent
// FTP:    src/ is child  of public/ → ROOT_DIR = __DIR__
define('ROOT_DIR', is_dir(dirname(__DIR__) . '/src') ? dirname(__DIR__) : __DIR__);

require_once ROOT_DIR . '/src/config.php';
require_once ROOT_DIR . '/src/Database.php';
require_once ROOT_DIR . '/src/Auth.php';
require_once ROOT_DIR . '/src/helpers.php';

Auth::start();

$action = $_GET['action'] ?? 'products';

// Logout
if ($action === 'logout') {
    Auth::logout();
    header('Location: ' . APP_BASE . '/?action=login');
    exit;
}

// Login POST
if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (Auth::login(trim($_POST['username'] ?? ''), $_POST['password'] ?? '')) {
        header('Location: ' . APP_BASE . '/');
        exit;
    }
    $loginError = 'Wrong username or password.';
}

// Login page (no auth required)
if ($action === 'login') {
    require_once ROOT_DIR . '/src/pages/login.php';
    exit;
}

// All other pages require auth
Auth::require();

if ($action === 'landings') {
    require_once ROOT_DIR . '/src/pages/landings.php';
} elseif ($action === 'testimonials') {
    require_once ROOT_DIR . '/src/pages/testimonials.php';
} else {
    require_once ROOT_DIR . '/src/pages/products.php';
}
