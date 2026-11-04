<?php
declare(strict_types=1);

namespace App\Services;

use App\Db;
use DateTimeImmutable;
use InvalidArgumentException;
use PDOException;
use RuntimeException;

/**
 * Import CSV degli export dei provider.
 *
 * ATTENZIONE: il mapping delle colonne (nome, data_scadenza, data_acquisto) e' costruito sulle fixture
 * tests/fixtures/aruba_sample.csv e serverplan_sample.csv, scritte a mano. Va verificato sugli export
 * reali di Aruba e Serverplan prima del rilascio. Le colonne di costo NON fanno parte dell'import.
 */
final class CsvImporter
{
    private const COLONNE = ['nome', 'data_scadenza', 'data_acquisto'];

    public static function parse(string $percorso, string $provider, ?string $nomeFile = null): ImportPreview
    {
        if (!in_array($provider, ['aruba', 'serverplan'], true)) {
            throw new InvalidArgumentException('Provider non supportato.');
        }
        $fh = @fopen($percorso, 'rb');
        if ($fh === false) {
            throw new RuntimeException('File non leggibile.');
        }
        $p = new ImportPreview([], [], [], $provider, $nomeFile ?? basename($percorso));
        try {
            $prima = fgets($fh);
            if ($prima === false) {
                $p->errori[1] = 'Il file e\' vuoto.';
                return $p;
            }
            $prima = preg_replace('/^\xEF\xBB\xBF/', '', $prima) ?? $prima;
            $sep = substr_count($prima, ';') >= substr_count($prima, ',') ? ';' : ',';
            $intest = array_map(static fn($c) => mb_strtolower(trim((string) $c), 'UTF-8'), str_getcsv($prima, $sep, '"', ''));
            $idx = [];
            foreach (self::COLONNE as $c) {
                $pos = array_search($c, $intest, true);
                if ($pos !== false) {
                    $idx[$c] = $pos;
                }
            }
            foreach (['nome', 'data_scadenza'] as $obbl) {
                if (!isset($idx[$obbl])) {
                    $p->errori[1] = "Colonna obbligatoria mancante: $obbl.";
                    return $p;
                }
            }

            $visti = [];
            $riga = 1;
            while (($r = fgetcsv($fh, 0, $sep, '"', '')) !== false) {
                $riga++;
                if ($r === [null] || $r === []) {
                    continue; // riga vuota
                }
                $nome = NomeDominio::normalizza((string) ($r[$idx['nome']] ?? ''));
                if ($nome === '') {
                    $p->errori[$riga] = 'Nome dominio mancante.';
                    continue;
                }
                if (NomeDominio::errore($nome) !== null) {
                    $p->errori[$riga] = 'Nome dominio non valido: ' . mb_substr(preg_replace('/[\x00-\x1f\x7f]+/u', ' ', $nome) ?? '', 0, 80, 'UTF-8') . '.';
                    continue;
                }
                $scad = self::data((string) ($r[$idx['data_scadenza']] ?? ''));
                if ($scad === null || $scad === false) {
                    $p->errori[$riga] = "Data di scadenza " . ($scad === null ? 'mancante' : 'non valida') . " per $nome.";
                    continue;
                }
                $acq = isset($idx['data_acquisto']) ? self::data((string) ($r[$idx['data_acquisto']] ?? '')) : null;
                if ($acq === false) {
                    $p->errori[$riga] = "Data di acquisto non valida per $nome.";
                    continue;
                }
                if (isset($visti[$nome])) {
                    $p->errori[$riga] = "Dominio $nome duplicato nel file (gia' alla riga {$visti[$nome]}).";
                    continue;
                }
                $visti[$nome] = $riga;
                $voce = ['nome' => $nome, 'provider' => $provider, 'data_acquisto' => $acq,
                    'data_scadenza' => $scad, 'riga' => $riga];
                $id = self::idPerNome($nome);
                if ($id === null) {
                    $p->nuovi[] = $voce;
                } else {
                    $p->aggiornati[] = ['id' => $id] + $voce;
                }
            }
        } finally {
            fclose($fh);
        }
        return $p;
    }

    /** @return array{ok:int,errore:int} */
    public static function applica(ImportPreview $p, int $utenteId): array
    {
        $pdo = Db::pdo();
        $ok = 0;
        $errore = count($p->errori);
        $pdo->beginTransaction();
        try {
            $esistente = $pdo->prepare('SELECT * FROM domini WHERE id = ?');
            foreach ($p->nuovi as $v) {
                try {
                    self::salva(['nome' => $v['nome'], 'provider' => $v['provider'], 'stato' => 'attivo']
                        + self::date($v));
                    $ok++;
                } catch (PDOException) {
                    $errore++; // es. dominio creato nel frattempo: non blocca le altre righe
                }
            }
            foreach ($p->aggiornati as $v) {
                $esistente->execute([$v['id']]);
                $att = $esistente->fetch();
                if (!$att) {
                    $errore++;
                    continue;
                }
                // Si parte dalla riga esistente: cliente, stato, DNS e note restano; i costi non vengono toccati.
                unset($att['costo_acquisto'], $att['prezzo_rivendita']);
                $dati = ['provider' => $v['provider']] + self::date($v) + $att;
                $dati['data_acquisto'] = $v['data_acquisto'] ?? $att['data_acquisto'];
                try {
                    self::salva($dati);
                    $ok++;
                } catch (PDOException) {
                    $errore++;
                }
            }
            $log = $pdo->prepare("INSERT INTO import_log (utente_id, tipo, file, righe_ok, righe_errore) VALUES (?, 'import', ?, ?, ?)");
            $log->execute([$utenteId > 0 ? $utenteId : null, mb_substr($p->file, 0, 255, 'UTF-8'), $ok, $errore]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        return ['ok' => $ok, 'errore' => $errore];
    }

    private static function salva(array $dati): void
    {
        (new \App\Repos\DominioRepo())->save($dati);
    }

    private static function date(array $v): array
    {
        return ['data_acquisto' => $v['data_acquisto'], 'data_scadenza' => $v['data_scadenza']];
    }

    private static function idPerNome(string $nome): ?int
    {
        $st = Db::pdo()->prepare('SELECT id FROM domini WHERE nome = ?');
        $st->execute([$nome]);
        $id = $st->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    /** @return string|null|false data ISO; null se vuota; false se non valida */
    private static function data(string $s): string|null|false
    {
        $s = trim($s);
        if ($s === '') {
            return null;
        }
        foreach (['!d/m/Y', '!Y-m-d'] as $fmt) {
            $d = DateTimeImmutable::createFromFormat($fmt, $s);
            if ($d !== false && $d->format(substr($fmt, 1)) === $s) {
                return $d->format('Y-m-d');
            }
        }
        return false;
    }
}
