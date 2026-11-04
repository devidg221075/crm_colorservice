<?php
// Valori predefiniti. Le credenziali vanno in config/local.php (non versionato).
return [
    'app' => [
        'nome' => 'Gestionale domini',
        'base_url' => '',
        'timezone' => 'Europe/Rome',
        // true se l'app sta dietro un proxy TLS e $_SERVER['HTTPS'] resta vuoto.
        'cookie_secure' => false,
    ],
    'mail' => [
        'from' => 'noreply@localhost',
    ],
    'db' => [
        'driver' => 'mysql',
        'dsn' => 'mysql:host=localhost;dbname=gestionale;charset=utf8mb4',
        'user' => '',
        'pass' => '',
    ],
];
