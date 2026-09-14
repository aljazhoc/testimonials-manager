<?php
$root = __DIR__;

echo "<h2>PHP " . PHP_VERSION . "</h2>";
echo "<p>PHP >= 7.4: " . (version_compare(PHP_VERSION, '7.4', '>=') ? '<b style="color:green">OK</b>' : '<b style="color:red">NAPAKA</b>') . "</p>";
echo "<p>pdo_mysql: "  . (extension_loaded('pdo_mysql') ? '<b style="color:green">OK</b>' : '<b style="color:red">MANJKA</b>') . "</p>";
echo "<p>gd: "         . (extension_loaded('gd')        ? '<b style="color:green">OK</b>' : '<b style="color:red">MANJKA</b>') . "</p>";
echo "<p>ROOT_DIR: <code>$root</code></p>";
echo "<p>src/ obstaja: " . (is_dir($root . '/src') ? '<b style="color:green">DA</b>' : '<b style="color:red">NE</b>') . "</p>";

// .env check
$envFile = $root . '/.env';
echo "<p>.env obstaja: " . (file_exists($envFile) ? '<b style="color:green">DA</b>' : '<b style="color:red">NE — ustvari .env datoteko!</b>') . "</p>";

if (file_exists($envFile)) {
    $env = [];
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strpos(trim($line), '#') === 0 || strpos($line, '=') === false) continue;
        [$k, $v] = explode('=', $line, 2);
        $env[trim($k)] = trim($v);
    }

    $host = $env['DB_HOST'] ?? 'localhost';
    $name = $env['DB_NAME'] ?? '';
    $user = $env['DB_USER'] ?? '';
    $pass = $env['DB_PASSWORD'] ?? '';

    echo "<p>DB_HOST: <code>$host</code></p>";
    echo "<p>DB_NAME: <code>$name</code></p>";
    echo "<p>DB_USER: <code>$user</code></p>";
    echo "<p>DB_PASSWORD: <code>" . str_repeat('*', strlen($pass)) . "</code></p>";

    // Test DB connection
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        echo "<p>DB konekcija: <b style='color:green'>OK</b></p>";

        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        echo "<p>Tabele: <code>" . implode(', ', $tables) . "</code></p>";

        $landingCount = $pdo->query("SELECT COUNT(*) FROM landings")->fetchColumn();
        echo "<p>Landings v bazi: <b>$landingCount</b></p>";
    } catch (Exception $e) {
        echo "<p>DB konekcija: <b style='color:red'>NAPAKA — " . htmlspecialchars($e->getMessage()) . "</b></p>";
    }
}
