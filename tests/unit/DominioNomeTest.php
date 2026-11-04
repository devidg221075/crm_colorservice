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
}
