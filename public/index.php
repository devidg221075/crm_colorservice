<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Config;
use App\Router;

Config::load();

$router = new Router();
$router->add('GET', '/', function (): void {
    echo htmlspecialchars((string) Config::get('app.nome'), ENT_QUOTES, 'UTF-8');
});

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
