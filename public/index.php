<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Auth;
use App\Config;
use App\Controllers\AuthController;
use App\Controllers\ClientiController;
use App\Router;

Config::load();
Auth::startSession();

$router = new Router();
$router->add('GET', '/', function (): void {
    echo htmlspecialchars((string) Config::get('app.nome'), ENT_QUOTES, 'UTF-8');
});

$auth = new AuthController();
$router->add('GET', '/login', fn() => $auth->mostraLogin());
$router->add('POST', '/login', fn() => $auth->login());
$router->add('POST', '/logout', fn() => $auth->logout());

$clienti = new ClientiController();
$router->add('GET', '/clienti', fn() => $clienti->lista());
$router->add('GET', '/clienti/nuovo', fn() => $clienti->nuovo());
$router->add('POST', '/clienti', fn() => $clienti->crea());
$router->add('GET', '/clienti/{id}/modifica', fn(string $id) => $clienti->modifica($id));
$router->add('POST', '/clienti/{id}', fn(string $id) => $clienti->aggiorna($id));

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
