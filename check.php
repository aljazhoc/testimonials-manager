<?php
echo "<h2>PHP " . PHP_VERSION . "</h2>";
echo "<p>PHP >= 8.0: " . (version_compare(PHP_VERSION, '8.0', '>=') ? '<b style="color:green">OK</b>' : '<b style="color:red">NAPAKA — potreben PHP 8.0+</b>') . "</p>";
echo "<p>pdo_mysql: "  . (extension_loaded('pdo_mysql') ? '<b style="color:green">OK</b>' : '<b style="color:red">MANJKA</b>') . "</p>";
echo "<p>gd: "         . (extension_loaded('gd')        ? '<b style="color:green">OK</b>' : '<b style="color:red">MANJKA</b>') . "</p>";
echo "<p>fileinfo: "   . (extension_loaded('fileinfo')  ? '<b style="color:green">OK</b>' : '<b style="color:red">MANJKA</b>') . "</p>";
echo "<p>mod_rewrite: ". (function_exists('apache_get_modules') && in_array('mod_rewrite', apache_get_modules()) ? '<b style="color:green">OK</b>' : '<b style="color:orange">Ni mogoče preveriti</b>') . "</p>";
echo "<p>ROOT_DIR test: " . __DIR__ . "</p>";
echo "<p>src/ obstaja: " . (is_dir(__DIR__ . '/src') ? '<b style="color:green">DA</b>' : '<b style="color:red">NE — src/ mapa ni naložena!</b>') . "</p>";
echo "<p>uploads/ je pisljiv: " . (is_writable(__DIR__ . '/uploads') ? '<b style="color:green">DA</b>' : '<b style="color:red">NE — nastavi chmod 755</b>') . "</p>";
