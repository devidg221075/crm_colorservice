<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Config;
use App\Controllers\DominiController;
use App\Csrf;
use App\Db;
use App\HttpException;
use PDO;
use PHPUnit\Framework\TestCase;

final class DominiControllerTest extends TestCase
{
    private int $dominioId;

    protected function setUp(): void
    {
        Config::load(__DIR__ . '/fixtures/cfg');
        $_SESSION = [];
        $_POST = [];
        $_GET = [];
        $pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('CREATE TABLE utenti (id INTEGER PRIMARY KEY AUTOINCREMENT, nome TEXT, email TEXT UNIQUE,
            password_hash TEXT, ruolo TEXT, attivo INTEGER NOT NULL DEFAULT 1)');
        $pdo->exec("INSERT INTO utenti (nome, email, password_hash, ruolo) VALUES ('Admin','a@x.test','h','admin'), ('Collab','c@x.test','h','collaboratore')");
        $pdo->exec('CREATE TABLE clienti (id INTEGER PRIMARY KEY AUTOINCREMENT, ragione_sociale TEXT NOT NULL,
            note TEXT NULL, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');
        $pdo->exec("CREATE TABLE domini (id INTEGER PRIMARY KEY AUTOINCREMENT, cliente_id INTEGER NULL,
            nome TEXT NOT NULL UNIQUE, provider TEXT NOT NULL DEFAULT 'altro', data_acquisto TEXT NULL,
            data_scadenza TEXT NULL, stato TEXT NOT NULL DEFAULT 'attivo', costo_acquisto REAL NULL,
            prezzo_rivendita REAL NULL, nameserver TEXT NULL, note_dns TEXT NULL, codice_auth TEXT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
        $pdo->exec('CREATE TABLE titolari (id INTEGER PRIMARY KEY AUTOINCREMENT, dominio_id INTEGER NOT NULL,
            ragione_sociale TEXT NULL, cf_piva TEXT NULL, indirizzo TEXT NULL, pec TEXT NULL, email TEXT NULL, telefono TEXT NULL)');
        $pdo->exec("INSERT INTO domini (nome, provider, stato, data_scadenza, costo_acquisto, prezzo_rivendita, codice_auth)
            VALUES ('esempio.it', 'aruba', 'attivo', '2020-01-01', 7.5, 12.0, 'EPP-SEGRETO')");
        $this->dominioId = (int) $pdo->lastInsertId();
        $pdo->exec("INSERT INTO titolari (dominio_id, ragione_sociale) VALUES ({$this->dominioId}, 'Titolare Srl')");
        Db::setPdo($pdo);
    }

    protected function tearDown(): void
    {
        Db::setPdo(null);
        $_SESSION = [];
        $_POST = [];
        $_GET = [];
        http_response_code(200);
        Config::load(__DIR__ . '/fixtures/cfg');
    }

    private function loginComeAdmin(): void
    {
        $_SESSION['utente_id'] = 1;
    }

    private function loginComeCollaboratore(): void
    {
        $_SESSION['utente_id'] = 2;
    }

    private function html(callable $fn): string
    {
        ob_start();
        try {
            $fn();
        } finally {
            $out = (string) ob_get_clean();
        }
        return $out;
    }

    private function assertNessunCosto(string $html): void
    {
        foreach (['7,50', '12,00', '4,50', '7.5', 'Margine', 'costo_acquisto', 'prezzo_rivendita', 'Costo di acquisto', 'Prezzo di rivendita'] as $s) {
            $this->assertStringNotContainsString($s, $html, "Il campo '$s' non deve comparire per il collaboratore");
        }
    }

    public function test_collaboratore_non_vede_costi_in_lista_scheda_e_form(): void
    {
        $this->loginComeCollaboratore();
        $c = new DominiController();
        $lista = $this->html(fn() => $c->lista());
        $scheda = $this->html(fn() => $c->scheda((string) $this->dominioId));
        $form = $this->html(fn() => $c->modifica((string) $this->dominioId));
        $this->assertStringContainsString('esempio.it', $lista);
        $this->assertStringContainsString('EPP-SEGRETO', $scheda);
        $this->assertStringContainsString('Titolare Srl', $scheda);
        foreach ([$lista, $scheda, $form] as $html) {
            $this->assertNessunCosto($html);
        }
    }

    public function test_admin_vede_costi_e_margine(): void
    {
        $this->loginComeAdmin();
        $c = new DominiController();
        $lista = $this->html(fn() => $c->lista());
        $scheda = $this->html(fn() => $c->scheda((string) $this->dominioId));
        $this->assertStringContainsString('4,50', $lista);
        $this->assertStringContainsString('7,50', $scheda);
        $this->assertStringContainsString('4,50', $this->html(fn() => $c->margine((string) $this->dominioId)));
    }

    public function test_rotta_margine_403_per_collaboratore_e_401_senza_login(): void
    {
        $c = new DominiController();
        foreach ([[null, 401], [2, 403]] as [$uid, $status]) {
            $_SESSION = $uid === null ? [] : ['utente_id' => $uid];
            try {
                $this->html(fn() => $c->margine((string) $this->dominioId));
                $this->fail('Attesa HttpException');
            } catch (HttpException $e) {
                $this->assertSame($status, $e->status);
            }
        }
    }

    public function test_ogni_rotta_richiede_login(): void
    {
        $c = new DominiController();
        $azioni = [
            fn() => $c->lista(), fn() => $c->nuovo(), fn() => $c->crea(),
            fn() => $c->scheda('1'), fn() => $c->modifica('1'), fn() => $c->aggiorna('1'),
        ];
        foreach ($azioni as $a) {
            try {
                $this->html($a);
                $this->fail('Attesa HttpException 401');
            } catch (HttpException $e) {
                $this->assertSame(401, $e->status);
            }
        }
    }

    public function test_post_senza_csrf_viene_rifiutato(): void
    {
        $this->loginComeAdmin();
        $_POST = ['nome' => 'nuovo.it', 'provider' => 'aruba', 'stato' => 'attivo'];
        $this->html(fn() => (new DominiController())->crea());
        $this->assertSame(400, http_response_code());
        $this->assertSame(1, (int) Db::pdo()->query('SELECT COUNT(*) FROM domini')->fetchColumn());
    }

    public function test_collaboratore_non_puo_scrivere_i_costi(): void
    {
        $this->loginComeCollaboratore();
        $_POST = ['csrf' => Csrf::token(), 'nome' => 'esempio.it', 'provider' => 'aruba', 'stato' => 'attivo',
            'costo_acquisto' => '1', 'prezzo_rivendita' => '2', 'titolare_ragione_sociale' => 'Nuovo Titolare'];
        $this->html(fn() => (new DominiController())->aggiorna((string) $this->dominioId));
        $r = Db::pdo()->query('SELECT costo_acquisto, prezzo_rivendita FROM domini WHERE id = ' . $this->dominioId)->fetch();
        $this->assertEquals(7.5, $r['costo_acquisto']);
        $this->assertEquals(12.0, $r['prezzo_rivendita']);
        $t = Db::pdo()->query('SELECT ragione_sociale FROM titolari WHERE dominio_id = ' . $this->dominioId)->fetch();
        $this->assertSame('Nuovo Titolare', $t['ragione_sociale']);
    }

    public function test_collaboratore_crea_dominio_senza_costi(): void
    {
        $this->loginComeCollaboratore();
        $_POST = ['csrf' => Csrf::token(), 'nome' => ' Nuovo.IT. ', 'provider' => 'serverplan', 'stato' => 'attivo',
            'costo_acquisto' => '9', 'prezzo_rivendita' => '99'];
        $this->html(fn() => (new DominiController())->crea());
        $r = Db::pdo()->query("SELECT costo_acquisto, prezzo_rivendita FROM domini WHERE nome = 'nuovo.it'")->fetch();
        $this->assertNull($r['costo_acquisto']);
        $this->assertNull($r['prezzo_rivendita']);
    }

    public function test_admin_scrive_costi_con_virgola_decimale(): void
    {
        $this->loginComeAdmin();
        $_POST = ['csrf' => Csrf::token(), 'nome' => 'esempio.it', 'provider' => 'aruba', 'stato' => 'attivo',
            'costo_acquisto' => '8,25', 'prezzo_rivendita' => '15'];
        $this->html(fn() => (new DominiController())->aggiorna((string) $this->dominioId));
        $r = Db::pdo()->query('SELECT costo_acquisto, prezzo_rivendita FROM domini WHERE id = ' . $this->dominioId)->fetch();
        $this->assertEquals(8.25, $r['costo_acquisto']);
        $this->assertEquals(15.0, $r['prezzo_rivendita']);
    }

    public function test_stato_suggerito_mostrato_ma_non_applicato(): void
    {
        $this->loginComeCollaboratore();
        $html = $this->html(fn() => (new DominiController())->scheda((string) $this->dominioId));
        $this->assertStringContainsString('Stato suggerito', $html);
        $this->assertStringContainsString('scaduto', $html);
        $this->assertSame('attivo', Db::pdo()->query('SELECT stato FROM domini WHERE id = ' . $this->dominioId)->fetchColumn());
    }

    public function test_nome_duplicato_da_errore_di_validazione(): void
    {
        $this->loginComeAdmin();
        $_POST = ['csrf' => Csrf::token(), 'nome' => 'ESEMPIO.it', 'provider' => 'aruba', 'stato' => 'attivo'];
        $html = $this->html(fn() => (new DominiController())->crea());
        $this->assertSame(422, http_response_code());
        $this->assertStringContainsString('Esiste gia', $html);
    }

    public function test_output_escapato(): void
    {
        $this->loginComeCollaboratore();
        Db::pdo()->exec("UPDATE titolari SET ragione_sociale = '<script>x</script>'");
        $html = $this->html(fn() => (new DominiController())->scheda((string) $this->dominioId));
        $this->assertStringNotContainsString('<script>x', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }
}
