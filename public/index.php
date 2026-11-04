<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Auth;
use App\Config;
use App\Controllers\AuthController;
use App\Controllers\ClientiController;
use App\Controllers\DominiController;
use App\Controllers\ImportController;
use App\Controllers\NotificheController;
use App\Controllers\PrivacyController;
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

$domini = new DominiController();
$router->add('GET', '/domini', fn() => $domini->lista());
$router->add('GET', '/domini/nuovo', fn() => $domini->nuovo());
$router->add('POST', '/domini', fn() => $domini->crea());
$router->add('GET', '/domini/{id}', fn(string $id) => $domini->scheda($id));
$router->add('GET', '/domini/{id}/modifica', fn(string $id) => $domini->modifica($id));
$router->add('GET', '/domini/{id}/margine', fn(string $id) => $domini->margine($id));
$router->add('POST', '/domini/{id}', fn(string $id) => $domini->aggiorna($id));

$import = new ImportController();
$router->add('GET', '/import', fn() => $import->mostra());
$router->add('POST', '/import', fn() => $import->anteprima());
$router->add('POST', '/import/conferma', fn() => $import->conferma());

$notifiche = new NotificheController();
$router->add('GET', '/notifiche', fn() => $notifiche->lista());
$router->add('POST', '/notifiche/lette', fn() => $notifiche->segnaLette());

$privacy = new PrivacyController();
$router->add('GET', '/privacy', fn() => $privacy->mostra());
$router->add('POST', '/privacy/cerca', fn() => $privacy->cerca());
$router->add('POST', '/privacy/cancella', fn() => $privacy->cancella());

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');