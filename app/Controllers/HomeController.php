<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;

final class HomeController
{
    /** Pagina di arrivo: chi e' autenticato va ai domini, gli altri al login. */
    public static function destinazione(): string
    {
        return Auth::user() !== null ? '/domini' : '/login';
    }

    public function indice(): void
    {
        header('Location: ' . self::destinazione());
    }
}
