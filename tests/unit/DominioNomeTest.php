<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Services\NomeDominio;
use PHPUnit\Framework\TestCase;

final class DominioNomeTest extends TestCase
{
    public function test_normalizza_nome(): void
    {
        $this->assertSame('esempio.it', NomeDominio::normalizza(' Esempio.IT. '));
        $this->assertSame('esempio.it', NomeDominio::normalizza('esempio.it'));
    }

    public function test_nomi_validi(): void
    {
        foreach (['esempio.it', 'sub-dominio.esempio.co.uk', 'xn--perch-fsa.it', 'a1.b2'] as $n) {
            $this->assertNull(NomeDominio::errore(NomeDominio::normalizza($n)), $n);
        }
        $this->assertSame('esempio.it', NomeDominio::normalizza('Esempio.IT.'));
        $this->assertNull(NomeDominio::errore(NomeDominio::normalizza('Esempio.IT.')));
    }

    public function test_nomi_non_validi(): void
    {
        foreach (["esempio .it", "esem\npio.it", "esem\0pio.it", "a\tb.it", 'esempio', '-esempio.it', 'esempio-.it',
                  'es..empio.it', 'esempio.it/x', 'esem_pio.it', str_repeat('a', 64) . '.it'] as $n) {
            $this->assertNotNull(NomeDominio::errore(NomeDominio::normalizza($n)), json_encode($n));
        }
    }

    public function test_valida_rifiuta_nome_con_spazi(): void
    {
        $dati = ['nome' => 'esempio .it', 'provider' => 'aruba', 'stato' => 'attivo'];
        $this->assertNotSame([], \App\Repos\DominioRepo::valida($dati));
        $dati['nome'] = 'Esempio.IT.';
        $this->assertSame([], \App\Repos\DominioRepo::valida($dati));
    }
}
