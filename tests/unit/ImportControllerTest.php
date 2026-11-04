<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Config;
use App\Controllers\ImportController;
use App\Csrf;
use App\Db;
use App\HttpException;
use App\Services\CsvImporter;
use PDO;
use PHPUnit\Framework\TestCase;

final class ImportControllerTest extends TestCase
{
    private PDO $pdo;

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
        $this->pdo->exec("CREATE TABLE domini (id INTEGER PRIMARY KEY AUTOINCREMENT, cliente_id INTEGER NULL,
            nome TEXT NOT NULL UNIQUE, provider TEXT NOT NULL DEFAULT 'altro', data_acquisto TEXT NULL,
            data_scadenza TEXT NULL, stato TEXT NOT NULL DEFAULT 'attivo', costo_acquisto REAL NULL,
            prezzo_rivendita REAL NULL, nameserver TEXT NULL, note_dns TEXT NULL, codice_auth TEXT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
        $this->pdo->exec("CREATE TABLE import_log (id INTEGER PRIMARY KEY AUTOINCREMENT, utente_id INTEGER NULL,
            tipo TEXT NOT NULL DEFAULT 'import', file TEXT NOT NULL, data TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            righe_ok INTEGER NOT NULL DEFAULT 0, righe_errore INTEGER NOT NULL DEFAULT 0)");
        Db::setPdo($this->pdo);
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

    public function test_solo_admin_per_tutte_le_rotte(): void
    {
        $c = new ImportController();
        foreach ([[null, 401], [2, 403]] as [$uid, $status]) {
            $_SESSION = $uid === null ? [] : ['utente_id' => $uid];
            foreach (['mostra', 'anteprima', 'conferma'] as $m) {
                try {
                    $this->html(fn() => $c->$m());
                    $this->fail("Attesa HttpException per $m");
                } catch (HttpException $e) {
                    $this->assertSame($status, $e->status);
                }
            }
        }
    }

    public function test_post_senza_csrf_e_rifiutato_e_non_scrive(): void
    {
        $_SESSION = ['utente_id' => 1, 'import_preview' => CsvImporter::parse(__DIR__ . '/../fixtures/aruba_sample.csv', 'aruba')->toArray()];
        $c = new ImportController();
        $this->html(fn() => $c->conferma());
        $this->assertSame(400, http_response_code());
        $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM domini')->fetchColumn());
    }

    public function test_conferma_applica_e_la_sessione_si_svuota(): void
    {
        $_SESSION = ['utente_id' => 1, 'import_preview' => CsvImporter::parse(__DIR__ . '/../fixtures/aruba_sample.csv', 'aruba')->toArray()];
        $_POST['csrf'] = Csrf::token();
        $c = new ImportController();
        $out = $this->html(fn() => $c->conferma());
        $this->assertStringContainsString('2 righe importate', $out);
        $this->assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM domini')->fetchColumn());
        $this->assertArrayNotHasKey('import_preview', $_SESSION);
        $this->assertSame(1, (int) $this->pdo->query('SELECT utente_id FROM import_log')->fetchColumn());
    }

    public function test_anteprima_senza_file_csv_valido(): void
    {
        $_SESSION = ['utente_id' => 1];
        $_POST = ['csrf' => Csrf::token(), 'provider' => 'aruba'];
        $_FILES = ['file' => ['name' => 'x.xlsx', 'tmp_name' => '/nope', 'error' => UPLOAD_ERR_OK, 'size' => 10]];
        $out = $this->html(fn() => (new ImportController())->anteprima());
        $_FILES = [];
        $this->assertSame(422, http_response_code());
        $this->assertStringContainsString('solo file .csv', $out);
    }
}
