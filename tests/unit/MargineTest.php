<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Margine;
use PHPUnit\Framework\TestCase;

final class MargineTest extends TestCase
{
    public function test_margine_con_valori_mancanti_e_null(): void
    {
        $this->assertNull(Margine::calcola(null, 12.0));
        $this->assertNull(Margine::calcola(7.5, null));
        $this->assertSame(4.5, Margine::calcola(7.5, 12.0));
    }

    public function test_margine_arrotondato_a_due_decimali_e_puo_essere_negativo(): void
    {
        $this->assertSame(0.3, Margine::calcola(0.1, 0.4));
        $this->assertSame(-2.0, Margine::calcola(10.0, 8.0));
    }
}
