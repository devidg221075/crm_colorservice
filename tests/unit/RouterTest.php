<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Router;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    public function test_parametri_di_percorso_passati_al_handler(): void
    {
        $r = new Router();
        $visto = null;
        $r->add('GET', '/clienti/{id}/modifica', function (string $id) use (&$visto): void {
            $visto = $id;
        });
        $r->dispatch('GET', '/clienti/42/modifica?x=1');
        $this->assertSame('42', $visto);
    }

    public function test_rotta_esatta_ha_precedenza_sul_parametro(): void
    {
        $r = new Router();
        $chiamata = '';
        $r->add('GET', '/clienti/nuovo', function () use (&$chiamata): void { $chiamata = 'nuovo'; });
        $r->add('GET', '/clienti/{id}', function (string $id) use (&$chiamata): void { $chiamata = 'id'; });
        $r->dispatch('GET', '/clienti/nuovo');
        $this->assertSame('nuovo', $chiamata);
    }

    public function test_eccezione_generica_da_500_senza_messaggio(): void
    {
        $log = tempnam(sys_get_temp_dir(), 'log');
        $vecchio = ini_set('error_log', $log);
        $r = new Router();
        $r->add('GET', '/boom', function (): void {
            throw new \RuntimeException('SQLSTATE mysql:host=db.interno password=segreta');
        });
        ob_start();
        try {
            $r->dispatch('GET', '/boom');
        } finally {
            $out = (string) ob_get_clean();
            ini_set('error_log', (string) $vecchio);
        }
        $this->assertSame(500, http_response_code());
        http_response_code(200);
        $this->assertStringNotContainsString('SQLSTATE', $out);
        $this->assertStringNotContainsString('segreta', $out);
        $this->assertStringNotContainsString('RuntimeException', $out);
        $this->assertStringContainsString('Errore', $out);
        $this->assertStringContainsString('SQLSTATE', (string) file_get_contents($log));
        unlink($log);
    }

    public function test_http_exception_mantiene_lo_stato(): void
    {
        $r = new Router();
        $r->add('GET', '/vietato', function (): void {
            throw new \App\HttpException(403, 'Accesso negato');
        });
        ob_start();
        $r->dispatch('GET', '/vietato');
        $out = (string) ob_get_clean();
        $this->assertSame(403, http_response_code());
        http_response_code(200);
        $this->assertSame('Accesso negato', $out);
    }
}
