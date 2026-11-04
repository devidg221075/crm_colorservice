<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Auth;
use App\Config;
use App\Csrf;
use App\Db;
use App\HttpException;
use PDO;
use PHPUnit\Framework\TestCase;

final class AuthTest extends TestCase
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
        $ins = $pdo->prepare('INSERT INTO utenti (nome, email, password_hash, ruolo, attivo) VALUES (?,?,?,?,?)');
        $ins->execute(['Simone', 'simone@example.test', password_hash('segreta', PASSWORD_DEFAULT), 'admin', 1]);
        $ins->execute(['Collab', 'collab@example.test', password_hash('segreta', PASSWORD_DEFAULT), 'collaboratore', 1]);
        $ins->execute(['Disattivo', 'off@example.test', password_hash('segreta', PASSWORD_DEFAULT), 'collaboratore', 0]);
        Db::setPdo($pdo);
    }

    protected function tearDown(): void
    {
        Db::setPdo(null);
        $_SESSION = [];
        Config::load(__DIR__ . '/fixtures/cfg');
    }

    private function expectHttpStatus(int $status, callable $fn): void
    {
        try {
            $fn();
        } catch (HttpException $e) {
            $this->assertSame($status, $e->status);
            return;
        }
        $this->fail("Atteso HTTP $status, nessuna eccezione");
    }

    public function test_login_corretto(): void
    {
        $this->assertTrue(Auth::attempt('simone@example.test', 'segreta'));
        $u = Auth::user();
        $this->assertSame('Simone', $u['nome']);
        $this->assertSame('admin', $u['ruolo']);
        $this->assertArrayHasKey('id', $u);
    }

    public function test_password_errata_rifiutata(): void
    {
        $this->assertFalse(Auth::attempt('simone@example.test', 'sbagliata'));
        $this->assertNull(Auth::user());
    }

    public function test_email_sconosciuta_rifiutata(): void
    {
        $this->assertFalse(Auth::attempt('nessuno@example.test', 'segreta'));
    }

    public function test_utente_disattivato_non_accede(): void
    {
        $this->assertFalse(Auth::attempt('off@example.test', 'segreta'));
        $this->assertNull(Auth::user());
    }

    public function test_collaboratore_non_passa_requireRole_admin(): void
    {
        Auth::attempt('collab@example.test', 'segreta');
        $this->expectHttpStatus(403, fn() => Auth::requireRole('admin'));
    }

    public function test_admin_passa_requireRole_admin(): void
    {
        Auth::attempt('simone@example.test', 'segreta');
        Auth::requireRole('admin');
        $this->addToAssertionCount(1);
    }

    public function test_requireLogin_senza_sessione_da_401(): void
    {
        $this->expectHttpStatus(401, fn() => Auth::requireLogin());
    }

    public function test_logout_svuota_utente(): void
    {
        Auth::attempt('simone@example.test', 'segreta');
        Auth::logout();
        $this->assertNull(Auth::user());
    }

    public function test_csrf_token_stabile_e_verificato(): void
    {
        $t = Csrf::token();
        $this->assertSame($t, Csrf::token());
        $this->assertTrue(Csrf::check($t));
        $this->assertFalse(Csrf::check('altro'));
        $this->assertFalse(Csrf::check(''));
    }
}
