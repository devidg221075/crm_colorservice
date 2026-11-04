<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Csrf;
use App\View;
use App\Repos\ClienteRepo;

final class ClientiController
{
    private ClienteRepo $repo;

    public function __construct(?ClienteRepo $repo = null)
    {
        $this->repo = $repo ?? new ClienteRepo();
    }

    public function lista(): void
    {
        Auth::requireLogin();
        $clienti = $this->repo->all();
        View::render('clienti/lista', ['clienti' => $clienti]);
    }

    public function nuovo(): void
    {
        Auth::requireLogin();
        $this->mostraForm(['ragione_sociale' => '', 'note' => ''], []);
    }

    public function crea(): void
    {
        Auth::requireLogin();
        $this->salva(null);
    }

    public function modifica(string $id): void
    {
        Auth::requireLogin();
        $cliente = $this->repo->find((int) $id);
        if ($cliente === null) {
            $this->nonTrovato();
            return;
        }
        $this->mostraForm($cliente, []);
    }

    public function aggiorna(string $id): void
    {
        Auth::requireLogin();
        $this->salva((int) $id);
    }

    private function salva(?int $id): void
    {
        if ($id !== null && $this->repo->find($id) === null) {
            $this->nonTrovato();
            return;
        }
        if (!Csrf::check((string) ($_POST['csrf'] ?? ''))) {
            http_response_code(400);
            $this->mostraForm($this->daPost($id), ['Sessione scaduta, riprova.']);
            return;
        }
        $dati = $this->daPost($id);
        $errori = ClienteRepo::valida($dati);
        if ($errori !== []) {
            http_response_code(422);
            $this->mostraForm($dati, $errori);
            return;
        }
        $dati['ragione_sociale'] = trim($dati['ragione_sociale']);
        $this->repo->save($dati);
        header('Location: /clienti');
    }

    private function daPost(?int $id): array
    {
        $dati = [
            'ragione_sociale' => (string) ($_POST['ragione_sociale'] ?? ''),
            'note' => (string) ($_POST['note'] ?? ''),
        ];
        if ($id !== null) {
            $dati['id'] = $id;
        }
        return $dati;
    }

    private function mostraForm(array $cliente, array $errori): void
    {
        View::render('clienti/form', ['cliente' => $cliente, 'errori' => $errori]);
    }

    private function nonTrovato(): void
    {
        http_response_code(404);
        echo 'Cliente non trovato';
    }
}
