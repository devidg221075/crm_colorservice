<?php
declare(strict_types=1);

namespace App\Repos;

use App\Db;

final class TitolareRepo
{
    public const CAMPI = ['ragione_sociale', 'cf_piva', 'indirizzo', 'pec', 'email', 'telefono'];

    public function perDominio(int $dominioId): ?array
    {
        $st = Db::pdo()->prepare('SELECT id, dominio_id, ragione_sociale, cf_piva, indirizzo, pec, email, telefono
            FROM titolari WHERE dominio_id = ? ORDER BY id LIMIT 1');
        $st->execute([$dominioId]);
        return $st->fetch() ?: null;
    }

    /** Un titolare per dominio: aggiorna quello esistente oppure lo crea. */
    public function salva(int $dominioId, array $dati): void
    {
        $v = [];
        foreach (self::CAMPI as $c) {
            $s = trim((string) ($dati[$c] ?? ''));
            $v[$c] = $s === '' ? null : $s;
        }
        $pdo = Db::pdo();
        $esistente = $this->perDominio($dominioId);
        if ($esistente === null) {
            $st = $pdo->prepare('INSERT INTO titolari (dominio_id, ragione_sociale, cf_piva, indirizzo, pec, email, telefono)
                VALUES (?, ?, ?, ?, ?, ?, ?)');
            $st->execute([$dominioId, $v['ragione_sociale'], $v['cf_piva'], $v['indirizzo'], $v['pec'], $v['email'], $v['telefono']]);
            return;
        }
        $st = $pdo->prepare('UPDATE titolari SET ragione_sociale = ?, cf_piva = ?, indirizzo = ?, pec = ?, email = ?, telefono = ?
            WHERE id = ?');
        $st->execute([$v['ragione_sociale'], $v['cf_piva'], $v['indirizzo'], $v['pec'], $v['email'], $v['telefono'], (int) $esistente['id']]);
    }
}
