<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h3>Step 1: Basic info</h3>";
echo "PHP: " . PHP_VERSION . "<br>";
echo "ROOT: " . __DIR__ . "<br>";

echo "<h3>Step 2: Load config</h3>";
define('ROOT_DIR', __DIR__);
try {
    require_once __DIR__ . '/src/config.php';
    echo "config.php: OK<br>";
    echo "APP_BASE: '" . APP_BASE . "'<br>";
    echo "DB_NAME: " . DB_NAME . "<br>";
    echo "UPLOAD_URL: " . UPLOAD_URL . "<br>";
} catch (Throwable $e) {
    echo "config.php ERROR: " . $e->getMessage() . "<br>";
    exit;
}

echo "<h3>Step 3: Load Database</h3>";
try {
    require_once __DIR__ . '/src/Database.php';
    $db = Database::get();
    echo "Database: OK<br>";
} catch (Throwable $e) {
    echo "Database ERROR: " . $e->getMessage() . "<br>";
    exit;
}

echo "<h3>Step 4: Load Auth + helpers</h3>";
try {
    require_once __DIR__ . '/src/Auth.php';
    require_once __DIR__ . '/src/helpers.php';
    echo "Auth + helpers: OK<br>";
} catch (Throwable $e) {
    echo "Auth/helpers ERROR: " . $e->getMessage() . "<br>";
    exit;
}

echo "<h3>Step 5: renderHeader test</h3>";
try {
    session_start();
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'test';
    renderHeader('Test');
    echo "<p>renderHeader: OK</p>";
    renderFooter();
} catch (Throwable $e) {
    echo "renderHeader/Footer ERROR: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "<br>";
}
