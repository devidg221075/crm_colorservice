<?php
declare(strict_types=1);

namespace App\Repos;

use App\Db;

final class NotificaRepo
{
    public function crea(int $utenteId, string $testo, ?int $dominioId): void
    {
        $st = Db::pdo()->prepare('INSERT INTO notifiche (utente_id, testo, dominio_id) VALUES (?, ?, ?)');
        $st->execute([$utenteId, $testo, $dominioId]);
    }
}
