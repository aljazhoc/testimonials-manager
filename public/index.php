<?php

define('ROOT_DIR', dirname(__DIR__));

require_once ROOT_DIR . '/src/config.php';
require_once ROOT_DIR . '/src/Database.php';
require_once ROOT_DIR . '/src/Auth.php';
require_once ROOT_DIR . '/src/helpers.php';

Auth::start();

$action = $_GET['action'] ?? 'products';

// Logout
if ($action === 'logout') {
    Auth::logout();
    header('Location: /?action=login');
    exit;
}

// Login POST
if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (Auth::login(trim($_POST['username'] ?? ''), $_POST['password'] ?? '')) {
        header('Location: /');
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

match ($action) {
    'landings'     => require_once ROOT_DIR . '/src/pages/landings.php',
    'testimonials' => require_once ROOT_DIR . '/src/pages/testimonials.php',
    default        => require_once ROOT_DIR . '/src/pages/products.php',
};
