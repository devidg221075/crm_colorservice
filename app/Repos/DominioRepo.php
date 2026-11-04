<?php
declare(strict_types=1);

namespace App\Repos;

use App\Db;
use App\Services\Margine;
use App\Services\NomeDominio;
use App\Services\Stato;
use DateTimeImmutable;

final class DominioRepo
{
    public const PROVIDER = ['aruba', 'serverplan', 'altro'];

    private const COLONNE = 'd.id, d.cliente_id, d.nome, d.provider, d.data_acquisto, d.data_scadenza, d.stato,
        d.nameserver, d.note_dns, d.codice_auth, d.created_at, d.updated_at';
    private const COLONNE_COSTI = ', d.costo_acquisto, d.prezzo_rivendita';

    /**
     * La selezione SQL dipende dal ruolo: senza $conCosti le colonne di costo non vengono nemmeno lette.
     * $conCosti va passato true solo per l'admin.
     */
    public function find(int $id, bool $conCosti = false): ?array
    {
        $st = Db::pdo()->prepare('SELECT ' . self::COLONNE . ($conCosti ? self::COLONNE_COSTI : '') . ',
            c.ragione_sociale AS cliente_nome
            FROM domini d LEFT JOIN clienti c ON c.id = d.cliente_id WHERE d.id = ?');
        $st->execute([$id]);
        $r = $st->fetch();
        return $r ? ($conCosti ? $this->conMargine($r) : $r) : null;
    }

    /** @param array{q?:string,stato?:string,provider?:string,cliente_id?:int|string} $filtri */
    public function lista(array $filtri, bool $conCosti = false): array
    {
        $sql = 'SELECT ' . self::COLONNE . ($conCosti ? self::COLONNE_COSTI : '') . ',
            c.ragione_sociale AS cliente_nome
            FROM domini d LEFT JOIN clienti c ON c.id = d.cliente_id WHERE 1 = 1';
        $p = [];
        $q = trim((string) ($filtri['q'] ?? ''));
        if ($q !== '') {
            $sql .= " AND d.nome LIKE ? ESCAPE '\\'";
            $p[] = '%' . addcslashes(mb_strtolower($q, 'UTF-8'), '%_\\') . '%';
        }
        if (($filtri['stato'] ?? '') !== '') {
            $sql .= ' AND d.stato = ?';
            $p[] = (string) $filtri['stato'];
        }
        if (($filtri['provider'] ?? '') !== '') {
            $sql .= ' AND d.provider = ?';
            $p[] = (string) $filtri['provider'];
        }
        if (($filtri['cliente_id'] ?? '') !== '') {
            $sql .= ' AND d.cliente_id = ?';
            $p[] = (int) $filtri['cliente_id'];
        }
        $sql .= ' ORDER BY d.nome';
        $st = Db::pdo()->prepare($sql);
        $st->execute($p);
        $righe = $st->fetchAll();
        return $conCosti ? array_map([$this, 'conMargine'], $righe) : $righe;
    }

    /**
     * Inserisce se `id` e' assente, altrimenti aggiorna. I costi vengono scritti solo se le chiavi
     * `costo_acquisto` / `prezzo_rivendita` sono presenti in $dati (il controller le passa solo per l'admin).
     */
    public function save(array $dati): int
    {
        $pdo = Db::pdo();
        $comuni = [
            $dati['cliente_id'] ?? null ?: null,
            NomeDominio::normalizza((string) ($dati['nome'] ?? '')),
            (string) ($dati['provider'] ?? 'altro'),
            self::nullSeVuoto($dati['data_acquisto'] ?? null),
            self::nullSeVuoto($dati['data_scadenza'] ?? null),
            (string) ($dati['stato'] ?? 'attivo'),
            self::nullSeVuoto($dati['nameserver'] ?? null),
            self::nullSeVuoto($dati['note_dns'] ?? null),
            self::nullSeVuoto($dati['codice_auth'] ?? null),
        ];
        $conCosti = array_key_exists('costo_acquisto', $dati) || array_key_exists('prezzo_rivendita', $dati);
        $costi = [$dati['costo_acquisto'] ?? null, $dati['prezzo_rivendita'] ?? null];

        if (empty($dati['id'])) {
            $st = $pdo->prepare('INSERT INTO domini (cliente_id, nome, provider, data_acquisto, data_scadenza, stato,
                nameserver, note_dns, codice_auth, costo_acquisto, prezzo_rivendita) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $st->execute(array_merge($comuni, $costi));
            return (int) $pdo->lastInsertId();
        }
        $id = (int) $dati['id'];
        $st = $pdo->prepare('UPDATE domini SET cliente_id = ?, nome = ?, provider = ?, data_acquisto = ?, data_scadenza = ?,
            stato = ?, nameserver = ?, note_dns = ?, codice_auth = ? WHERE id = ?');
        $st->execute(array_merge($comuni, [$id]));
        if ($conCosti) {
            $st = $pdo->prepare('UPDATE domini SET costo_acquisto = ?, prezzo_rivendita = ? WHERE id = ?');
            $st->execute(array_merge($costi, [$id]));
        }
        return $id;
    }

    /** @return string[] messaggi di errore (vuoto se i dati sono validi) */
    public static function valida(array $dati): array
    {
        $errori = [];
        $nome = NomeDominio::normalizza((string) ($dati['nome'] ?? ''));
        if ($nome === '') {
            $errori[] = 'Il nome del dominio e\' obbligatorio.';
        } elseif (mb_strlen($nome, 'UTF-8') > 253) {
            $errori[] = 'Il nome del dominio non puo\' superare 253 caratteri.';
        } elseif (($e = NomeDominio::errore($nome)) !== null) {
            $errori[] = $e;
        }
        if (!in_array($dati['provider'] ?? '', self::PROVIDER, true)) {
            $errori[] = 'Provider non valido.';
        }
        if (!in_array($dati['stato'] ?? '', Stato::VALORI, true)) {
            $errori[] = 'Stato non valido.';
        }
        foreach (['data_acquisto' => 'di acquisto', 'data_scadenza' => 'di scadenza'] as $campo => $et) {
            $v = self::nullSeVuoto($dati[$campo] ?? null);
            if ($v !== null && !self::dataValida($v)) {
                $errori[] = "La data $et non e' valida (usa AAAA-MM-GG).";
            }
        }
        foreach (['costo_acquisto' => 'Il costo di acquisto', 'prezzo_rivendita' => 'Il prezzo di rivendita'] as $campo => $et) {
            $v = $dati[$campo] ?? null;
            if ($v !== null && (!is_numeric($v) || (float) $v < 0)) {
                $errori[] = "$et deve essere un numero maggiore o uguale a zero.";
            }
        }
        return $errori;
    }

    private function conMargine(array $r): array
    {
        $a = isset($r['costo_acquisto']) ? (float) $r['costo_acquisto'] : null;
        $v = isset($r['prezzo_rivendita']) ? (float) $r['prezzo_rivendita'] : null;
        $r['margine'] = Margine::calcola($a, $v);
        return $r;
    }

    private static function nullSeVuoto(mixed $v): ?string
    {
        $s = trim((string) ($v ?? ''));
        return $s === '' ? null : $s;
    }

    private static function dataValida(string $s): bool
    {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $s);
        return $d !== false && $d->format('Y-m-d') === $s;
    }
}
