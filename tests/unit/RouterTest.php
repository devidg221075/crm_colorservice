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
}
