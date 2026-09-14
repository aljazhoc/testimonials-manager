<?php

defined('ROOT_DIR') || define('ROOT_DIR', dirname(__DIR__));

// Load .env file if present
$envFile = ROOT_DIR . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$key, $val] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($val);
    }
}

// DB
define('DB_HOST',     $_ENV['DB_HOST']     ?? 'localhost');
define('DB_NAME',     $_ENV['DB_NAME']     ?? 'testimonials');
define('DB_USER',     $_ENV['DB_USER']     ?? 'app_user');
define('DB_PASSWORD', $_ENV['DB_PASSWORD'] ?? '');

// External API
define('API_URL', $_ENV['API_URL'] ?? '');
define('API_KEY', $_ENV['API_KEY'] ?? '');

// Uploads
define('UPLOAD_DIR',      ROOT_DIR . '/public/uploads');
define('UPLOAD_URL',      '/uploads/');
define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024); // 5 MB

// Pagination
define('PER_PAGE', 15);
