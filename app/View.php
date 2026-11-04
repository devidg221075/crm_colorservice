<?php
declare(strict_types=1);

namespace App;

final class View
{
    /**
     * Renderizza un frammento di vista dentro views/layout.php.
     * Il frammento puo' impostare $titolo; in alternativa vale il titolo passato.
     */
    public static function render(string $vista, array $dati, string $titolo = ''): void
    {
        $base = dirname(__DIR__) . '/views/';
        (static function () use ($base, $vista, $dati, $titolo): void {
            extract($dati, EXTR_SKIP);
            ob_start();
            try {
                require $base . $vista . '.php';
                $contenuto = (string) ob_get_contents();
            } finally {
                ob_end_clean();
            }
            require $base . 'layout.php';
        })();
    }
}
