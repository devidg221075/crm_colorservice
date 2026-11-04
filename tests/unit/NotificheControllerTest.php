<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Config;
use App\Controllers\NotificheController;
use App\Csrf;
use App\Db;
use App\HttpException;
use App\Repos\NotificaRepo;
use PDO;
use PHPUnit\Framework\TestCase;

final class NotificheControllerTest extends TestCase
{
    protected function setUp(): void
    {
        Config::load(__DIR__ . '/fixtures/cfg');
        $_SESSION = [];
        $_POST = [];
        $pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('CREATE TABLE utenti (id INTEGER PRIMARY KEY AUTOINCREMENT, nome TEXT, email TEXT UNIQUE,
            password_hash TEXT, ruolo TEXT, attivo INTEGER NOT NULL DEFAULT 1)');
        $pdo->exec("INSERT INTO utenti (nome, email, password_hash, ruolo) VALUES ('Admin','a@x.test','h','admin'), ('Collab','c@x.test','h','collaboratore')");
        $pdo->exec('CREATE TABLE notifiche (id INTEGER PRIMARY KEY AUTOINCREMENT, utente_id INTEGER NOT NULL,
            testo TEXT NOT NULL, dominio_id INTEGER NULL, letta INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');
        Db::setPdo($pdo);
        $repo = new NotificaRepo();
        $repo->crea(1, 'Notifica <b>admin</b>', null);
        $repo->crea(2, 'Notifica collaboratore', null);
    }

    protected function tearDown(): void
    {
        Db::setPdo(null);
        $_SESSION = [];
        $_POST = [];
        http_response_code(200);
    }

    public function test_lista_richiede_login(): void
    {
        $this->expectException(HttpException::class);
        (new NotificheController())->lista();
    }

    public function test_lista_mostra_solo_le_proprie_notifiche_escapate_con_badge(): void
    {
        $_SESSION['utente_id'] = 1;
        ob_start();
        (new NotificheController())->lista();
        $out = (string) ob_get_clean();
        $this->assertStringContainsString('Notifica &lt;b&gt;admin&lt;/b&gt;', $out);
        $this->assertStringNotContainsString('Notifica collaboratore', $out);
        $this->assertStringContainsString('data-notifiche-badge', $out);
        $this->assertStringContainsString('action="/logout"', $out);
    }

    public function test_segna_lette_senza_csrf_non_modifica(): void
    {
        $_SESSION['utente_id'] = 1;
        ob_start();
        (new NotificheController())->segnaLette();
        ob_end_clean();
        $this->assertSame(400, http_response_code());
        $this->assertCount(1, (new NotificaRepo())->nonLette(1));
    }

    public function test_segna_lette_con_csrf_segna_solo_le_proprie(): void
    {
        $_SESSION['utente_id'] = 1;
        $_POST['csrf'] = Csrf::token();
        // L'utente_id nella richiesta va ignorato.
        $_POST['utente_id'] = '2';
        (new NotificheController())->segnaLette();
        $repo = new NotificaRepo();
        $this->assertCount(0, $repo->nonLette(1));
        $this->assertCount(1, $repo->nonLette(2));
    }
}
