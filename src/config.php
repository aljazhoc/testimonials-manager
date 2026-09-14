<?php

defined('ROOT_DIR') || define('ROOT_DIR', dirname(__DIR__));

// Load .env file if present
$envFile = ROOT_DIR . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strpos(trim($line), '#') === 0 || strpos($line, '=') === false) continue;
        [$key, $val] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($val);
    }
}

// DB
define('DB_HOST',     $_ENV['DB_HOST']     ?? 'localhost');
define('DB_PORT',     $_ENV['DB_PORT']     ?? '3306');
define('DB_NAME',     $_ENV['DB_NAME']     ?? 'testimonials');
define('DB_USER',     $_ENV['DB_USER']     ?? 'app_user');
define('DB_PASSWORD', $_ENV['DB_PASSWORD'] ?? '');

// External API
define('API_URL', $_ENV['API_URL'] ?? '');
define('API_KEY', $_ENV['API_KEY'] ?? '');

// Detect base URL path ('' at root, '/testimonials-manager' in subdir)
if (!defined('APP_BASE')) {
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
    $dir    = dirname($script);
    define('APP_BASE', $dir === '/' ? '' : rtrim($dir, '/'));
}

// Uploads — Docker: uploads/ is inside public/, FTP: uploads/ is in web root
$_publicDir = is_dir(ROOT_DIR . '/public') ? ROOT_DIR . '/public' : ROOT_DIR;
define('UPLOAD_DIR',      $_publicDir . '/uploads');
define('UPLOAD_URL',      APP_BASE . '/uploads/');
define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024); // 5 MB

// Pagination
define('PER_PAGE', 15);
