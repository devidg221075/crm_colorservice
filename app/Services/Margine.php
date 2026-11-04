<?php
declare(strict_types=1);

namespace App\Services;

final class Margine
{
    /** Margine = rivendita - acquisto, arrotondato a 2 decimali; null se manca uno dei due valori. */
    public static function calcola(?float $acquisto, ?float $rivendita): ?float
    {
        if ($acquisto === null || $rivendita === null) {
            return null;
        }
        return round($rivendita - $acquisto, 2);
    }
}
