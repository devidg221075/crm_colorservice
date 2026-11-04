<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Config;
use App\Controllers\HomeController;
use App\Db;
use PDO;
use PHPUnit\Framework\TestCase;

final class HomeControllerTest extends TestCase
{
    protected function setUp(): void
    {
        Config::load(__DIR__ . '/fixtures/cfg');
        $_SESSION = [];
        $pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('CREATE TABLE utenti (id INTEGER PRIMARY KEY AUTOINCREMENT, nome TEXT, email TEXT UNIQUE,
            password_hash TEXT, ruolo TEXT, attivo INTEGER NOT NULL DEFAULT 1)');
        $pdo->exec("INSERT INTO utenti (nome, email, password_hash, ruolo) VALUES ('Admin','a@x.test','h','admin')");
        Db::setPdo($pdo);
    }

    protected function tearDown(): void
    {
        Db::setPdo(null);
        $_SESSION = [];
    }

    public function test_utente_loggato_va_ai_domini(): void
    {
        $_SESSION['utente_id'] = 1;
        $this->assertSame('/domini', HomeController::destinazione());
    }

    public function test_anonimo_va_al_login(): void
    {
        $this->assertSame('/login', HomeController::destinazione());
    }

    public function test_utente_inesistente_in_sessione_va_al_login(): void
    {
        $_SESSION['utente_id'] = 99;
        $this->assertSame('/login', HomeController::destinazione());
    }
}
