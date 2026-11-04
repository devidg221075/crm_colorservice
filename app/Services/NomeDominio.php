<?php
declare(strict_types=1);

namespace App\Services;

final class NomeDominio
{
    private const REGEX = '/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/D';

    /** Minuscolo, senza spazi ai bordi e senza punto finale. */
    public static function normalizza(string $nome): string
    {
        return rtrim(mb_strtolower(trim($nome), 'UTF-8'), '.');
    }

    /**
     * Controlla un nome gia' normalizzato: deve essere un hostname plausibile (lettere, cifre, trattini,
     * almeno due etichette) oppure un IDN valido nella forma punycode (xn--).
     * @return string|null messaggio di errore in italiano, null se valido
     */
    public static function errore(string $nome): ?string
    {
        $msg = 'Il nome del dominio non e\' valido (solo lettere, numeri e trattini, senza spazi, es. esempio.it).';
        if ($nome === '') {
            return $msg;
        }
        $ascii = $nome;
        if (preg_match('/[^\x20-\x7e]/', $nome) === 1) {
            // Caratteri non ASCII: ammessi solo come IDN convertibile in punycode (richiede l'estensione intl).
            if (preg_match('/[\x00-\x20\x7f\p{Z}\p{C}]/u', $nome) === 1 || !function_exists('idn_to_ascii')) {
                return $msg;
            }
            $ascii = idn_to_ascii($nome, IDNA_NONTRANSITIONAL_TO_ASCII, INTL_IDNA_VARIANT_UTS46);
            if (!is_string($ascii) || $ascii === '') {
                return $msg;
            }
        }
        if (preg_match(self::REGEX, $ascii) !== 1) {
            return $msg;
        }
        foreach (explode('.', $ascii) as $etichetta) {
            if (strlen($etichetta) > 63) {
                return 'Ogni parte del nome del dominio non puo\' superare 63 caratteri.';
            }
        }
        return null;
    }
}
