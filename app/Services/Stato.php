<?php
declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;

final class Stato
{
    public const VALORI = ['attivo', 'in_scadenza', 'scaduto', 'trasferito'];
    public const SOGLIA_IN_SCADENZA = 60;

    /** Giorni di calendario da $oggi a $scadenza (negativo se gia' scaduto); l'ora e' ignorata. */
    public static function giorniAllaScadenza(DateTimeImmutable $oggi, DateTimeImmutable $scadenza): int
    {
        $a = $oggi->setTime(0, 0);
        $b = $scadenza->setTime(0, 0);
        return (int) $a->diff($b)->format('%r%a');
    }

    /** Stato suggerito all'utente: non viene mai applicato automaticamente. */
    public static function suggerito(int $giorni, string $statoAttuale): string
    {
        if ($statoAttuale === 'trasferito') {
            return 'trasferito';
        }
        if ($giorni < 0) {
            return 'scaduto';
        }
        if ($giorni <= self::SOGLIA_IN_SCADENZA) {
            return 'in_scadenza';
        }
        return $statoAttuale;
    }
}
