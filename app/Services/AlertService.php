<?php
declare(strict_types=1);

namespace App\Services;

use App\Db;
use App\Repos\DominioRepo;
use App\Repos\NotificaRepo;
use App\Repos\UtenteRepo;
use DateTimeImmutable;

final class AlertService
{
    public const SOGLIE = [60, 30, 15, 7];

    /**
     * Invia gli alert dovuti alla data $oggi e ritorna quanti (dominio, scadenza) ne sono stati inviati.
     * Per ogni dominio si invia una sola email, per la soglia piu' stretta raggiunta; le soglie piu'
     * larghe ancora non registrate vengono considerate coperte. Nulla viene registrato se l'invio fallisce.
     */
    public function run(DateTimeImmutable $oggi, Mailer $mailer): int
    {
        $utenti = (new UtenteRepo())->elencoAttivi();
        if ($utenti === []) {
            return 0;
        }
        $domini = array_merge(
            (new DominioRepo())->lista(['stato' => 'attivo']),
            (new DominioRepo())->lista(['stato' => 'in_scadenza'])
        );
        $inviati = 0;
        foreach ($domini as $d) {
            if (empty($d['data_scadenza'])) {
                continue;
            }
            $scadenza = new DateTimeImmutable((string) $d['data_scadenza']);
            $giorni = Stato::giorniAllaScadenza($oggi, $scadenza);
            if ($giorni < 0) {
                continue;
            }
            $dovute = array_values(array_filter(
                self::SOGLIE,
                fn (int $s): bool => $giorni <= $s && !$this->giaInviato((int) $d['id'], $s, $scadenza)
            ));
            if ($dovute === []) {
                continue;
            }
            if ($this->invia($d, $giorni, $scadenza, $utenti, $mailer)) {
                foreach ($dovute as $soglia) {
                    $this->registra((int) $d['id'], $soglia, $scadenza);
                }
                $inviati++;
            }
        }
        return $inviati;
    }

    private function invia(array $d, int $giorni, DateTimeImmutable $scadenza, array $utenti, Mailer $mailer): bool
    {
        $quando = $giorni === 0 ? 'oggi' : ($giorni === 1 ? 'domani' : "tra $giorni giorni");
        $data = $scadenza->format('d/m/Y');
        $testo = "Il dominio {$d['nome']} scade $quando ($data).";
        $oggetto = "Scadenza dominio {$d['nome']}: $quando";
        $corpo = $testo . "\n\nVerifica il rinnovo dal gestionale domini.";
        $notifiche = new NotificaRepo();
        $tuttiOk = true;
        foreach ($utenti as $u) {
            if ($mailer->invia((string) $u['email'], $oggetto, $corpo)) {
                $notifiche->crea((int) $u['id'], $testo, (int) $d['id']);
            } else {
                $tuttiOk = false;
            }
        }
        return $tuttiOk;
    }

    private function giaInviato(int $dominioId, int $soglia, DateTimeImmutable $scadenza): bool
    {
        $st = Db::pdo()->prepare('SELECT 1 FROM alert_inviati WHERE dominio_id = ? AND soglia_giorni = ? AND data_scadenza_riferimento = ?');
        $st->execute([$dominioId, $soglia, $scadenza->format('Y-m-d')]);
        return $st->fetchColumn() !== false;
    }

    private function registra(int $dominioId, int $soglia, DateTimeImmutable $scadenza): void
    {
        $st = Db::pdo()->prepare('INSERT INTO alert_inviati (dominio_id, soglia_giorni, data_scadenza_riferimento) VALUES (?, ?, ?)');
        $st->execute([$dominioId, $soglia, $scadenza->format('Y-m-d')]);
    }
}
