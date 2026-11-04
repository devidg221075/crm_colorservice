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

    /** Tutti gli utenti attivi (destinatari degli alert). */
    public function elencoAttivi(): array
    {
        return Db::pdo()->query('SELECT id, nome, email FROM utenti WHERE attivo = 1 ORDER BY id')->fetchAll();
    }

    /** Tutti gli utenti (attivi e non), senza hash della password. */
    public function elencoTutti(): array
    {
        return Db::pdo()->query('SELECT id, nome, email, ruolo, attivo FROM utenti ORDER BY nome, id')->fetchAll();
    }

    /** Utente (attivo o no) per id, senza hash della password. */
    public function trova(int $id): ?array
    {
        $st = Db::pdo()->prepare('SELECT id, nome, email, ruolo, attivo FROM utenti WHERE id = ?');
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    /** True se un altro utente (diverso da $escludiId) ha gia' questa email (confronto senza maiuscole). */
    public function emailInUso(string $email, ?int $escludiId = null): bool
    {
        $st = Db::pdo()->prepare('SELECT 1 FROM utenti WHERE LOWER(email) = LOWER(?) AND id <> ? LIMIT 1');
        $st->execute([$email, $escludiId ?? 0]);
        return $st->fetchColumn() !== false;
    }

    /** @param array{nome:string,email:string,ruolo:string,attivo:int} $dati */
    public function crea(array $dati, string $passwordHash): int
    {
        $st = Db::pdo()->prepare('INSERT INTO utenti (nome, email, password_hash, ruolo, attivo) VALUES (?,?,?,?,?)');
        $st->execute([$dati['nome'], $dati['email'], $passwordHash, $dati['ruolo'], $dati['attivo']]);
        return (int) Db::pdo()->lastInsertId();
    }

    /**
     * Aggiorna un utente; con $passwordHash null la password resta invariata.
     * @throws \DomainException se la modifica lascerebbe zero amministratori attivi.
     */
    public function aggiorna(int $id, array $dati, ?string $passwordHash): void
    {
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            $diventaAdminAttivo = $dati['ruolo'] === 'admin' && (int) $dati['attivo'] === 1;
            if (!$diventaAdminAttivo) {
                $st = $pdo->prepare("SELECT COUNT(*) FROM utenti WHERE ruolo = 'admin' AND attivo = 1 AND id <> ?");
                $st->execute([$id]);
                if ((int) $st->fetchColumn() === 0) {
                    throw new \DomainException('Deve restare almeno un amministratore attivo.');
                }
            }
            $set = 'nome = ?, email = ?, ruolo = ?, attivo = ?';
            $par = [$dati['nome'], $dati['email'], $dati['ruolo'], (int) $dati['attivo']];
            if ($passwordHash !== null) {
                $set .= ', password_hash = ?';
                $par[] = $passwordHash;
            }
            $par[] = $id;
            $pdo->prepare("UPDATE utenti SET $set WHERE id = ?")->execute($par);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
