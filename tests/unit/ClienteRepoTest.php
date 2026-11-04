<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Db;
use App\Repos\ClienteRepo;
use PDO;
use PHPUnit\Framework\TestCase;

final class ClienteRepoTest extends TestCase
{
    private ClienteRepo $repo;

    protected function setUp(): void
    {
        $pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('CREATE TABLE clienti (id INTEGER PRIMARY KEY AUTOINCREMENT, ragione_sociale TEXT NOT NULL,
            note TEXT NULL, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');
        Db::setPdo($pdo);
        $this->repo = new ClienteRepo();
    }

    protected function tearDown(): void
    {
        Db::setPdo(null);
    }

    public function test_salva_e_recupera_cliente(): void
    {
        $id = $this->repo->save(['ragione_sociale' => 'Rossi Srl', 'note' => '']);
        $this->assertSame('Rossi Srl', $this->repo->find($id)['ragione_sociale']);
    }

    public function test_save_con_id_aggiorna(): void
    {
        $id = $this->repo->save(['ragione_sociale' => 'Rossi Srl', 'note' => 'a']);
        $this->assertSame($id, $this->repo->save(['id' => $id, 'ragione_sociale' => 'Rossi SpA', 'note' => 'b']));
        $c = $this->repo->find($id);
        $this->assertSame('Rossi SpA', $c['ragione_sociale']);
        $this->assertSame('b', $c['note']);
        $this->assertCount(1, $this->repo->all());
    }

    public function test_find_inesistente_restituisce_null(): void
    {
        $this->assertNull($this->repo->find(999));
    }

    public function test_all_ordinato_per_ragione_sociale(): void
    {
        $this->repo->save(['ragione_sociale' => 'Zeta', 'note' => '']);
        $this->repo->save(['ragione_sociale' => 'Alfa', 'note' => '']);
        $this->assertSame(['Alfa', 'Zeta'], array_column($this->repo->all(), 'ragione_sociale'));
    }

    public function test_input_sql_non_viene_interpretato(): void
    {
        $id = $this->repo->save(['ragione_sociale' => "x'); DROP TABLE clienti;--", 'note' => '']);
        $this->assertSame("x'); DROP TABLE clienti;--", $this->repo->find($id)['ragione_sociale']);
    }

    public function test_validazione(): void
    {
        $this->assertSame([], ClienteRepo::valida(['ragione_sociale' => 'Rossi Srl']));
        $this->assertNotSame([], ClienteRepo::valida(['ragione_sociale' => '   ']));
        $this->assertNotSame([], ClienteRepo::valida([]));
        $this->assertSame([], ClienteRepo::valida(['ragione_sociale' => str_repeat('a', 191)]));
        $this->assertNotSame([], ClienteRepo::valida(['ragione_sociale' => str_repeat('a', 192)]));
        $this->assertSame([], ClienteRepo::valida(['ragione_sociale' => str_repeat('è', 191)]));
    }
}
