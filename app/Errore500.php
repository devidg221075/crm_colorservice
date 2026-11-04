<?php
declare(strict_types=1);

namespace App;

use Throwable;

/** Pagina 500 generica: il dettaglio va solo nel log, mai nella risposta. */
final class Errore500
{
    public static function mostra(Throwable $e): void
    {
        error_log('Errore non gestito: ' . $e::class . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()
            . "\n" . $e->getTraceAsString());
        if (!headers_sent()) {
            http_response_code(500);
        }
        echo '<!DOCTYPE html><html lang="it"><head><meta charset="utf-8"><title>Errore</title></head><body>'
            . '<h1>Errore</h1><p>Si e\' verificato un errore imprevisto. Riprova tra poco; se il problema persiste contatta l\'amministratore.</p>'
            . '</body></html>';
    }
}
