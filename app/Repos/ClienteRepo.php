<?php
declare(strict_types=1);

namespace App\Repos;

use App\Db;

final class ClienteRepo
{
    public const MAX_RAGIONE_SOCIALE = 191;

    public function all(): array
    {
        return Db::pdo()->query('SELECT id, ragione_sociale, note, created_at FROM clienti ORDER BY ragione_sociale')->fetchAll();
    }

    public function find(int $id): ?array
    {
        $st = Db::pdo()->prepare('SELECT id, ragione_sociale, note, created_at FROM clienti WHERE id = ?');
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    /** Inserisce se `id` e' assente, altrimenti aggiorna. Restituisce l'id del cliente. */
    public function save(array $dati): int
    {
        $ragione = (string) ($dati['ragione_sociale'] ?? '');
        $note = (string) ($dati['note'] ?? '');
        $pdo = Db::pdo();
        if (empty($dati['id'])) {
            $st = $pdo->prepare('INSERT INTO clienti (ragione_sociale, note) VALUES (?, ?)');
            $st->execute([$ragione, $note]);
            return (int) $pdo->lastInsertId();
        }
        $id = (int) $dati['id'];
        $st = $pdo->prepare('UPDATE clienti SET ragione_sociale = ?, note = ? WHERE id = ?');
        $st->execute([$ragione, $note, $id]);
        return $id;
    }

    /** @return string[] messaggi di errore (vuoto se i dati sono validi) */
    public static function valida(array $dati): array
    {
        $ragione = trim((string) ($dati['ragione_sociale'] ?? ''));
        if ($ragione === '') {
            return ['La ragione sociale e\' obbligatoria.'];
        }
        if (mb_strlen($ragione, 'UTF-8') > self::MAX_RAGIONE_SOCIALE) {
            return ['La ragione sociale non puo\' superare ' . self::MAX_RAGIONE_SOCIALE . ' caratteri.'];
        }
        return [];
    }
}
