<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Db;
use App\Repos\NotificaRepo;
use PDO;
use PHPUnit\Framework\TestCase;

final class NotificaRepoTest extends TestCase
{
    private NotificaRepo $repo;

    protected function setUp(): void
    {
        $pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('CREATE TABLE notifiche (id INTEGER PRIMARY KEY AUTOINCREMENT, utente_id INTEGER NOT NULL,
            testo TEXT NOT NULL, dominio_id INTEGER NULL, letta INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');
        Db::setPdo($pdo);
        $this->repo = new NotificaRepo();
    }

    protected function tearDown(): void
    {
        Db::setPdo(null);
    }

    public function test_segna_letto_azzera_non_lette(): void
    {
        $this->repo->crea(1, 'Scade fra 30 giorni', null);
        $this->repo->segnaLette(1);
        $this->assertCount(0, $this->repo->nonLette(1));
    }

    public function test_non_lette_restituisce_solo_quelle_dell_utente(): void
    {
        $this->repo->crea(1, 'Per uno', null);
        $this->repo->crea(2, 'Per due', null);
        $righe = $this->repo->nonLette(1);
        $this->assertCount(1, $righe);
        $this->assertSame('Per uno', $righe[0]['testo']);
    }

    public function test_segna_lette_non_tocca_le_notifiche_di_altri_utenti(): void
    {
        $this->repo->crea(1, 'Per uno', null);
        $this->repo->crea(2, 'Per due', null);
        $this->repo->segnaLette(1);
        $this->assertCount(0, $this->repo->nonLette(1));
        $this->assertCount(1, $this->repo->nonLette(2));
    }

    public function test_non_lette_piu_recenti_per_prime(): void
    {
        $this->repo->crea(1, 'Vecchia', null);
        $this->repo->crea(1, 'Nuova', null);
        $this->assertSame(['Nuova', 'Vecchia'], array_column($this->repo->nonLette(1), 'testo'));
    }
}
