<?php
declare(strict_types=1);

namespace App\Services;

final class NomeDominio
{
    /** Minuscolo, senza spazi ai bordi e senza punto finale. */
    public static function normalizza(string $nome): string
    {
        return rtrim(mb_strtolower(trim($nome), 'UTF-8'), '.');
    }
}
