<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Csrf;
use App\HttpException;
use App\Repos\NotificaRepo;

final class NotificheController
{
    private NotificaRepo $repo;

    public function __construct(?NotificaRepo $repo = null)
    {
        $this->repo = $repo ?? new NotificaRepo();
    }

    public function lista(): void
    {
        $utente = $this->utenteCorrente();
        $notifiche = $this->repo->nonLette($utente['id']);
        $titolo = 'Notifiche';
        ob_start();
        require dirname(__DIR__, 2) . '/views/notifiche/lista.php';
        $contenuto = (string) ob_get_clean();
        require dirname(__DIR__, 2) . '/views/layout.php';
    }

    public function segnaLette(): void
    {
        $utente = $this->utenteCorrente();
        if (!Csrf::check((string) ($_POST['csrf'] ?? ''))) {
            http_response_code(400);
            echo 'Sessione scaduta, riprova.';
            return;
        }
        $this->repo->segnaLette($utente['id']);
        header('Location: /notifiche');
    }

    /** Utente loggato (mai da input della richiesta); 401 se assente. */
    private function utenteCorrente(): array
    {
        $u = Auth::user();
        if ($u === null) {
            throw new HttpException(401, 'Accesso richiesto');
        }
        return $u;
    }
}
