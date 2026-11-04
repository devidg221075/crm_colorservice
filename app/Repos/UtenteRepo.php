<?php
declare(strict_types=1);

namespace App\Repos;

use App\Db;

final class UtenteRepo
{
    /** Utente attivo con questa email, oppure null. */
    public function trovaAttivoPerEmail(string $email): ?array
    {
        $st = Db::pdo()->prepare('SELECT id, nome, email, password_hash, ruolo FROM utenti WHERE email = ? AND attivo = 1');
        $st->execute([$email]);
        return $st->fetch() ?: null;
    }

    public function trovaAttivoPerId(int $id): ?array
    {
        $st = Db::pdo()->prepare('SELECT id, nome, email, ruolo FROM utenti WHERE id = ? AND attivo = 1');
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }
}
