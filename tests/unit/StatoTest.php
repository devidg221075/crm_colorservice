<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Stato;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class StatoTest extends TestCase
{
    public function test_giorni_alla_scadenza(): void
    {
        $this->assertSame(30, Stato::giorniAllaScadenza(new DateTimeImmutable('2026-11-04'), new DateTimeImmutable('2026-12-04')));
        $this->assertSame(-1, Stato::giorniAllaScadenza(new DateTimeImmutable('2026-11-04'), new DateTimeImmutable('2026-11-03')));
        $this->assertSame(0, Stato::giorniAllaScadenza(new DateTimeImmutable('2026-11-04 15:00'), new DateTimeImmutable('2026-11-04 08:00')));
    }

    public function test_suggerito(): void
    {
        $this->assertSame('scaduto', Stato::suggerito(-1, 'attivo'));
        $this->assertSame('in_scadenza', Stato::suggerito(0, 'attivo'));
        $this->assertSame('in_scadenza', Stato::suggerito(60, 'attivo'));
        $this->assertSame('attivo', Stato::suggerito(61, 'attivo'));
        $this->assertSame('scaduto', Stato::suggerito(400, 'scaduto'));
    }

    public function test_trasferito_non_viene_mai_cambiato(): void
    {
        foreach ([-10, 0, 30, 61, 500] as $g) {
            $this->assertSame('trasferito', Stato::suggerito($g, 'trasferito'));
        }
    }

    public function test_valori_stato(): void
    {
        $this->assertSame(['attivo', 'in_scadenza', 'scaduto', 'trasferito'], Stato::VALORI);
    }
}
