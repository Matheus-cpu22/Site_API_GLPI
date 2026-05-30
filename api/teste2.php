<?php
echo "Loaded ini: " . php_ini_loaded_file() . "<br>";
echo "Extension dir: " . ini_get('extension_dir') . "<br>";
echo "mbstring carregado? " . (extension_loaded('mbstring') ? 'SIM' : 'NAO') . "<br>";
echo "php: " . PHP_BINARY . "<br>";
phpinfo();