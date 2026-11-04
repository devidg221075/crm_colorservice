<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Auth;
use App\Config;
use App\Controllers\PrivacyController;
use App\Csrf;
use App\Db;
use App\HttpException;
use App\Repos\DominioRepo;
use App\Repos\TitolareRepo;
use App\Services\PrivacyService;
use App\View;
use PDO;
use PHPUnit\Framework\TestCase;

final class PrivacyServiceTest extends TestCase
{
    private PDO $pdo;
    private PrivacyService $service;
    private TitolareRepo $titolari;
    private DominioRepo $domini;

    protected function setUp(): void
    {
        Config::load(__DIR__ . '/fixtures/cfg');
        $_SESSION = [];
        $_POST = [];
        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->pdo->exec('CREATE TABLE utenti (id INTEGER PRIMARY KEY AUTOINCREMENT, nome TEXT, email TEXT UNIQUE,
            password_hash TEXT, ruolo TEXT, attivo INTEGER NOT NULL DEFAULT 1)');
        $this->pdo->exec("INSERT INTO utenti (nome, email, password_hash, ruolo) VALUES ('Admin','a@x.test','h','admin'), ('Collab','c@x.test','h','collaboratore')");
        $this->pdo->exec('CREATE TABLE clienti (id INTEGER PRIMARY KEY AUTOINCREMENT, ragione_sociale TEXT, note TEXT)');
        $this->pdo->exec("CREATE TABLE domini (id INTEGER PRIMARY KEY AUTOINCREMENT, cliente_id INTEGER NULL,
            nome TEXT NOT NULL UNIQUE, provider TEXT NOT NULL DEFAULT 'altro', data_acquisto TEXT NULL,
            data_scadenza TEXT NULL, stato TEXT NOT NULL DEFAULT 'attivo', costo_acquisto REAL NULL,
            prezzo_rivendita REAL NULL, nameserver TEXT NULL, note_dns TEXT NULL, codice_auth TEXT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
        $this->pdo->exec('CREATE TABLE titolari (id INTEGER PRIMARY KEY AUTOINCREMENT, dominio_id INTEGER NOT NULL,
            ragione_sociale TEXT NULL, cf_piva TEXT NULL, indirizzo TEXT NULL, pec TEXT NULL, email TEXT NULL, telefono TEXT NULL)');
        $this->pdo->exec("CREATE TABLE import_log (id INTEGER PRIMARY KEY AUTOINCREMENT, utente_id INTEGER NULL,
            tipo TEXT NOT NULL DEFAULT 'import', file TEXT NOT NULL, data TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            righe_ok INTEGER NOT NULL DEFAULT 0, righe_errore INTEGER NOT NULL DEFAULT 0)");
        $this->pdo->exec("CREATE TABLE notifiche (id INTEGER PRIMARY KEY AUTOINCREMENT, utente_id INTEGER NOT NULL,
            testo TEXT NOT NULL, dominio_id INTEGER NULL, letta INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
        $this->pdo->exec("INSERT INTO domini (id, nome) VALUES (1, 'uno.test'), (2, 'due.test'), (3, 'tre.test')");
        $this->pdo->exec("INSERT INTO titolari (dominio_id, ragione_sociale, cf_piva, indirizzo, pec, email, telefono) VALUES
            (1, 'Mario Rossi', 'RSSMRA80A01H501U', 'Via Roma 1', 'mario@pec.test', 'mario@example.test', '333'),
            (2, 'Mario Rossi', 'RSSMRA80A01H501U', 'Via Roma 1', 'mario@pec.test', 'Mario@Example.test', '333'),
            (3, 'Altro', 'X', 'Via Y', 'a@pec.test', 'altro@example.test', '444')");
        Db::setPdo($this->pdo);
        $this->titolari = new TitolareRepo();
        $this->domini = new DominioRepo();
        $this->service = new PrivacyService();
    }

    protected function tearDown(): void
    {
        Db::setPdo(null);
        $_SESSION = [];
        $_POST = [];
        http_response_code(200);
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

    public function test_esporta_restituisce_titolari_con_domini(): void
    {
        $r = $this->service->esporta('mario@example.test');
        $this->assertCount(2, $r);
        $this->assertSame('Mario Rossi', $r[0]['ragione_sociale']);
        $this->assertSame('uno.test', $r[0]['dominio']['nome']);
        $this->assertSame([], $this->service->esporta('nessuno@example.test'));
    }

    public function test_cancellazione_anonimizza_senza_eliminare_il_dominio(): void
    {
        $n = $this->service->cancella('mario@example.test');
        $this->assertSame(2, $n);
        $this->assertNotNull($this->domini->find(1));
        $this->assertNotNull($this->domini->find(2));
        $this->assertSame([], $this->titolari->findByEmail('mario@example.test'));
        $riga = $this->pdo->query('SELECT * FROM titolari WHERE dominio_id = 1')->fetch();
        foreach (TitolareRepo::CAMPI as $c) {
            $this->assertNull($riga[$c], $c);
        }
        // gli altri titolari restano intatti
        $this->assertSame('Altro', $this->pdo->query('SELECT ragione_sociale FROM titolari WHERE dominio_id = 3')->fetchColumn());
    }

    public function test_cancellazione_registra_import_log_senza_dati_personali(): void
    {
        $this->service->cancella('mario@example.test', 1);
        $log = $this->pdo->query('SELECT * FROM import_log')->fetchAll();
        $this->assertCount(1, $log);
        $this->assertSame('privacy_cancellazione', $log[0]['tipo']);
        $this->assertSame(1, (int) $log[0]['utente_id']);
        $this->assertSame(2, (int) $log[0]['righe_ok']);
        $this->assertStringNotContainsString('mario', strtolower(implode('|', $log[0])));
    }

    public function test_cancellazione_senza_corrispondenze_non_scrive_log(): void
    {
        $this->assertSame(0, $this->service->cancella('nessuno@example.test'));
        $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM import_log')->fetchColumn());
    }

    public function test_solo_admin_per_tutte_le_rotte(): void
    {
        $c = new PrivacyController();
        foreach ([[null, 401], [2, 403]] as [$uid, $status]) {
            $_SESSION = $uid === null ? [] : ['utente_id' => $uid];
            foreach (['mostra', 'cerca', 'cancella'] as $m) {
                try {
                    $this->html(fn() => $c->$m());
                    $this->fail("Attesa HttpException per $m");
                } catch (HttpException $e) {
                    $this->assertSame($status, $e->status);
                }
            }
        }
        $this->assertSame(2, (int) $this->pdo->query("SELECT COUNT(*) FROM titolari WHERE email LIKE '%mario@example.test'")->fetchColumn());
    }

    public function test_get_non_cancella_e_mostra_la_scheda(): void
    {
        $_SESSION = ['utente_id' => 1];
        $_GET = ['email' => 'mario@example.test'];
        $out = $this->html(fn() => (new PrivacyController())->mostra());
        $_GET = [];
        $this->assertStringContainsString('action="/logout"', $out);
        $this->assertSame(2, count($this->titolari->findByEmail('mario@example.test')));
    }

    public function test_cerca_senza_csrf_rifiutata(): void
    {
        $_SESSION = ['utente_id' => 1];
        $_POST = ['email' => 'mario@example.test'];
        $out = $this->html(fn() => (new PrivacyController())->cerca());
        $this->assertSame(400, http_response_code());
        $this->assertStringNotContainsString('Mario Rossi', $out);
    }

    public function test_cerca_mostra_dati_escapati(): void
    {
        $this->pdo->exec("UPDATE titolari SET ragione_sociale = '<script>x</script>' WHERE dominio_id = 1");
        $_SESSION = ['utente_id' => 1];
        $_POST = ['csrf' => Csrf::token(), 'email' => 'mario@example.test'];
        $out = $this->html(fn() => (new PrivacyController())->cerca());
        $this->assertStringContainsString('uno.test', $out);
        $this->assertStringContainsString('&lt;script&gt;', $out);
        $this->assertStringNotContainsString('<script>x', $out);
    }

    public function test_cancella_senza_csrf_o_senza_conferma_non_cancella(): void
    {
        $_SESSION = ['utente_id' => 1];
        $c = new PrivacyController();
        $_POST = ['email' => 'mario@example.test', 'conferma' => 'CANCELLA'];
        $this->html(fn() => $c->cancella());
        $this->assertSame(400, http_response_code());

        foreach (['', 'cancella ', 'SI'] as $conf) {
            $_POST = ['csrf' => Csrf::token(), 'email' => 'mario@example.test', 'conferma' => $conf];
            $this->html(fn() => $c->cancella());
            $this->assertSame(422, http_response_code());
        }
        $this->assertSame(2, count($this->titolari->findByEmail('mario@example.test')));
        $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM import_log')->fetchColumn());
    }

    public function test_cancella_confermata_anonimizza_e_registra_utente(): void
    {
        $_SESSION = ['utente_id' => 1];
        $_POST = ['csrf' => Csrf::token(), 'email' => 'mario@example.test', 'conferma' => 'CANCELLA'];
        $out = $this->html(fn() => (new PrivacyController())->cancella());
        $this->assertStringContainsString('2 titolari anonimizzati', $out);
        $this->assertSame([], $this->titolari->findByEmail('mario@example.test'));
        $this->assertSame(1, (int) $this->pdo->query('SELECT utente_id FROM import_log')->fetchColumn());
    }

    public function test_menu_admin_vede_import_e_privacy(): void
    {
        $_SESSION = ['utente_id' => 1];
        $out = $this->html(fn() => (new PrivacyController())->mostra());
        foreach (['/clienti', '/domini', '/import', '/privacy'] as $href) {
            $this->assertStringContainsString('href="' . $href . '"', $out);
        }
        $this->assertStringContainsString('action="/logout"', $out);
        $this->assertStringContainsString('href="/notifiche"', $out);
    }

    public function test_menu_collaboratore_non_vede_import_e_privacy(): void
    {
        $_SESSION = ['utente_id' => 2];
        $out = $this->html(fn() => View::render('import/anteprima', ['anteprima' => null, 'esito' => null, 'errori' => []]));
        foreach (['/clienti', '/domini'] as $href) {
            $this->assertStringContainsString('href="' . $href . '"', $out);
        }
        $this->assertStringNotContainsString('href="/import"', $out);
        $this->assertStringNotContainsString('href="/privacy"', $out);
        $this->assertStringContainsString('action="/logout"', $out);
    }

    public function test_cancella_confermata_senza_corrispondenze(): void
    {
        $_SESSION = ['utente_id' => 1];
        $_POST = ['csrf' => Csrf::token(), 'email' => 'nessuno@example.test', 'conferma' => 'CANCELLA'];
        $out = $this->html(fn() => (new PrivacyController())->cancella());
        $this->assertStringContainsString('Nessun titolare trovato', $out);
        $this->assertStringNotContainsString('anonimizzati', $out);
    }
}
