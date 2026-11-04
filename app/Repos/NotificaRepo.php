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

    /** Notifiche non lette dell'utente, le piu' recenti per prime. */
    public function nonLette(int $utenteId): array
    {
        $st = Db::pdo()->prepare('SELECT id, testo, dominio_id, created_at FROM notifiche
            WHERE utente_id = ? AND letta = 0 ORDER BY id DESC');
        $st->execute([$utenteId]);
        return $st->fetchAll();
    }

    public function segnaLette(int $utenteId): void
    {
        $st = Db::pdo()->prepare('UPDATE notifiche SET letta = 1 WHERE utente_id = ? AND letta = 0');
        $st->execute([$utenteId]);
    }
}
