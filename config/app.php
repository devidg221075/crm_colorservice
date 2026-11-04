<?php
// Valori predefiniti. Le credenziali vanno in config/local.php (non versionato).
return [
    'app' => [
        'nome' => 'Gestionale domini',
        'base_url' => '',
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
