<?php
declare(strict_types=1);

namespace App\Services;

use App\Db;
use App\Repos\DominioRepo;
use App\Repos\TitolareRepo;

/** Diritti del titolare dei dati: accesso (esportazione) e cancellazione (anonimizzazione). */
final class PrivacyService
{
    private TitolareRepo $titolari;
    private DominioRepo $domini;

    public function __construct(?TitolareRepo $titolari = null, ?DominioRepo $domini = null)
    {
        $this->titolari = $titolari ?? new TitolareRepo();
        $this->domini = $domini ?? new DominioRepo();
    }

    /** Tutti i titolari con quell'email, ciascuno con il dominio collegato (senza campi di costo). */
    public function esporta(string $email): array
    {
        $out = [];
        foreach ($this->titolari->findByEmail($email) as $t) {
            $d = $this->domini->find((int) $t['dominio_id']);
            $t['dominio'] = $d === null ? null : [
                'id' => (int) $d['id'],
                'nome' => $d['nome'],
                'provider' => $d['provider'],
                'data_scadenza' => $d['data_scadenza'],
                'stato' => $d['stato'],
            ];
            $out[] = $t;
        }
        return $out;
    }

    /**
     * Anonimizza (NULL) i dati personali di tutti i titolari con quell'email; il dominio resta.
     * Registra l'operazione in import_log senza salvare l'email. Restituisce i titolari anonimizzati.
     */
    public function cancella(string $email, ?int $utenteId = null): int
    {
        $e = mb_strtolower(trim($email), 'UTF-8');
        if ($e === '') {
            return 0;
        }
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare('UPDATE titolari SET ragione_sociale = NULL, cf_piva = NULL, indirizzo = NULL,
                pec = NULL, email = NULL, telefono = NULL WHERE LOWER(email) = ?');
            $st->execute([$e]);
            $n = $st->rowCount();
            if ($n > 0) {
                $log = $pdo->prepare("INSERT INTO import_log (utente_id, tipo, file, righe_ok, righe_errore)
                    VALUES (?, 'privacy_cancellazione', 'cancellazione dati titolare', ?, 0)");
                $log->execute([$utenteId !== null && $utenteId > 0 ? $utenteId : null, $n]);
            }
            $pdo->commit();
        } catch (\Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $ex;
        }
        return $n;
    }
}
