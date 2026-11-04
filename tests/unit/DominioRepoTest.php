<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Db;
use App\Repos\DominioRepo;
use App\Repos\TitolareRepo;
use PDO;
use PHPUnit\Framework\TestCase;

final class DominioRepoTest extends TestCase
{
    private DominioRepo $repo;

    protected function setUp(): void
    {
        $pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('CREATE TABLE clienti (id INTEGER PRIMARY KEY AUTOINCREMENT, ragione_sociale TEXT NOT NULL,
            note TEXT NULL, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');
        $pdo->exec("CREATE TABLE domini (id INTEGER PRIMARY KEY AUTOINCREMENT, cliente_id INTEGER NULL,
            nome TEXT NOT NULL UNIQUE, provider TEXT NOT NULL DEFAULT 'altro', data_acquisto TEXT NULL,
            data_scadenza TEXT NULL, stato TEXT NOT NULL DEFAULT 'attivo', costo_acquisto REAL NULL,
            prezzo_rivendita REAL NULL, nameserver TEXT NULL, note_dns TEXT NULL, codice_auth TEXT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
        $pdo->exec('CREATE TABLE titolari (id INTEGER PRIMARY KEY AUTOINCREMENT, dominio_id INTEGER NOT NULL,
            ragione_sociale TEXT NULL, cf_piva TEXT NULL, indirizzo TEXT NULL, pec TEXT NULL, email TEXT NULL, telefono TEXT NULL)');
        Db::setPdo($pdo);
        $this->repo = new DominioRepo();
    }

    protected function tearDown(): void
    {
        Db::setPdo(null);
    }

    private function nuovo(array $extra = []): int
    {
        return $this->repo->save($extra + [
            'nome' => ' Esempio.IT. ', 'provider' => 'aruba', 'stato' => 'attivo',
            'data_scadenza' => '2027-01-10', 'costo_acquisto' => 7.5, 'prezzo_rivendita' => 12.0,
            'codice_auth' => 'EPP-123',
        ]);
    }

    public function test_salva_normalizza_nome_e_recupera(): void
    {
        $id = $this->nuovo();
        $d = $this->repo->find($id, true);
        $this->assertSame('esempio.it', $d['nome']);
        $this->assertSame('EPP-123', $d['codice_auth']);
        $this->assertEquals(7.5, $d['costo_acquisto']);
    }

    public function test_senza_costi_find_e_lista_non_contengono_colonne_di_costo(): void
    {
        $id = $this->nuovo();
        $d = $this->repo->find($id);
        $this->assertArrayNotHasKey('costo_acquisto', $d);
        $this->assertArrayNotHasKey('prezzo_rivendita', $d);
        $this->assertArrayNotHasKey('margine', $d);
        $this->assertSame('EPP-123', $d['codice_auth']);
        $riga = $this->repo->lista([])[0];
        $this->assertArrayNotHasKey('costo_acquisto', $riga);
        $this->assertArrayNotHasKey('prezzo_rivendita', $riga);
        $this->assertArrayNotHasKey('margine', $riga);
    }

    public function test_admin_vede_costi_e_margine_in_lista(): void
    {
        $this->nuovo();
        $riga = $this->repo->lista([], true)[0];
        $this->assertEquals(7.5, $riga['costo_acquisto']);
        $this->assertSame(4.5, $riga['margine']);
    }

    public function test_save_senza_chiavi_costo_non_tocca_i_costi(): void
    {
        $id = $this->nuovo();
        $this->repo->save(['id' => $id, 'nome' => 'esempio.it', 'provider' => 'aruba', 'stato' => 'attivo', 'note_dns' => 'x']);
        $d = $this->repo->find($id, true);
        $this->assertEquals(7.5, $d['costo_acquisto']);
        $this->assertEquals(12.0, $d['prezzo_rivendita']);
        $this->assertSame('x', $d['note_dns']);
    }

    public function test_save_con_chiavi_costo_null_le_azzera(): void
    {
        $id = $this->nuovo();
        $this->repo->save(['id' => $id, 'nome' => 'esempio.it', 'provider' => 'aruba', 'stato' => 'attivo',
            'costo_acquisto' => null, 'prezzo_rivendita' => null]);
        $this->assertNull($this->repo->find($id, true)['costo_acquisto']);
    }

    public function test_filtri_lista(): void
    {
        Db::pdo()->exec("INSERT INTO clienti (ragione_sociale) VALUES ('Rossi')");
        $cid = (int) Db::pdo()->lastInsertId();
        $this->nuovo(['nome' => 'alfa.it', 'cliente_id' => $cid]);
        $this->nuovo(['nome' => 'beta.com', 'provider' => 'serverplan', 'stato' => 'scaduto']);
        $this->assertCount(2, $this->repo->lista([]));
        $this->assertSame(['alfa.it'], array_column($this->repo->lista(['q' => 'alf']), 'nome'));
        $this->assertSame(['beta.com'], array_column($this->repo->lista(['stato' => 'scaduto']), 'nome'));
        $this->assertSame(['beta.com'], array_column($this->repo->lista(['provider' => 'serverplan']), 'nome'));
        $this->assertSame(['alfa.it'], array_column($this->repo->lista(['cliente_id' => $cid]), 'nome'));
        $this->assertSame('Rossi', $this->repo->lista(['q' => 'alfa'])[0]['cliente_nome']);
    }

    public function test_input_sql_nei_filtri_non_viene_interpretato(): void
    {
        $this->nuovo();
        $this->assertSame([], $this->repo->lista(['q' => "x' OR '1'='1"]));
    }

    public function test_titolare_salvato_e_aggiornato(): void
    {
        $id = $this->nuovo();
        $t = new TitolareRepo();
        $this->assertNull($t->perDominio($id));
        $t->salva($id, ['ragione_sociale' => 'Rossi Srl', 'cf_piva' => '123', 'pec' => 'a@pec.it']);
        $t->salva($id, ['ragione_sociale' => 'Rossi SpA', 'cf_piva' => '123']);
        $this->assertSame('Rossi SpA', $t->perDominio($id)['ragione_sociale']);
        $this->assertSame(1, (int) Db::pdo()->query('SELECT COUNT(*) FROM titolari')->fetchColumn());
    }

    public function test_validazione(): void
    {
        $ok = ['nome' => 'a.it', 'provider' => 'aruba', 'stato' => 'attivo', 'data_scadenza' => '2027-01-01'];
        $this->assertSame([], DominioRepo::valida($ok));
        $this->assertNotSame([], DominioRepo::valida(['nome' => '  '] + $ok));
        $this->assertNotSame([], DominioRepo::valida(['provider' => 'x'] + $ok));
        $this->assertNotSame([], DominioRepo::valida(['stato' => 'boh'] + $ok));
        $this->assertNotSame([], DominioRepo::valida(['data_scadenza' => '2027-02-30'] + $ok));
        $this->assertNotSame([], DominioRepo::valida(['costo_acquisto' => -1] + $ok));
    }
}
