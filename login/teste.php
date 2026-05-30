<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

define('GLPI_URL', 'http://localhost:8080/apirest.php');
define('APP_TOKEN', 'uQCNSdQlUjayuF8dSQ3xdFsFT28OCNKbisjEf2QQ');
define('USER_TOKEN', 'YmCG22I83QeeWNLx1zYskDn2Pw8eGxCcJwEvoXcb');

$curl = curl_init();

curl_setopt_array($curl, [
    CURLOPT_URL => GLPI_URL . '/initSession',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'App-Token: ' . APP_TOKEN,
        'Authorization: user_token ' . USER_TOKEN
    ]
]);

$response = curl_exec($curl);

echo '<pre>';
print_r($response);
echo '</pre>';