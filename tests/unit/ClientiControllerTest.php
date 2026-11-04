<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Config;
use App\Controllers\ClientiController;
use App\Db;
use PDO;
use PHPUnit\Framework\TestCase;

final class ClientiControllerTest extends TestCase
{
    protected function setUp(): void
    {
        Config::load(__DIR__ . '/fixtures/cfg');
        $_SESSION = ['utente_id' => 1];
        $pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('CREATE TABLE utenti (id INTEGER PRIMARY KEY AUTOINCREMENT, nome TEXT, email TEXT UNIQUE,
            password_hash TEXT, ruolo TEXT, attivo INTEGER NOT NULL DEFAULT 1)');
        $pdo->exec("INSERT INTO utenti (nome, email, password_hash, ruolo) VALUES ('Admin','a@x.test','h','admin')");
        $pdo->exec('CREATE TABLE clienti (id INTEGER PRIMARY KEY AUTOINCREMENT, ragione_sociale TEXT NOT NULL,
            note TEXT NULL, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');
        $pdo->exec('CREATE TABLE notifiche (id INTEGER PRIMARY KEY AUTOINCREMENT, utente_id INTEGER NOT NULL,
            testo TEXT NOT NULL, dominio_id INTEGER NULL, letta INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');
        $pdo->exec("INSERT INTO clienti (ragione_sociale) VALUES ('<Acme>')");
        $pdo->exec("INSERT INTO notifiche (utente_id, testo) VALUES (1, 'x')");
        Db::setPdo($pdo);
    }

    protected function tearDown(): void
    {
        Db::setPdo(null);
        $_SESSION = [];
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

    public function test_pagine_clienti_nel_layout_con_badge_e_logout(): void
    {
        $c = new ClientiController();
        foreach ([fn() => $c->lista(), fn() => $c->nuovo(), fn() => $c->modifica('1')] as $f) {
            $out = $this->html($f);
            $this->assertStringContainsString('data-notifiche-badge', $out);
            $this->assertStringContainsString('action="/logout"', $out);
            $this->assertSame(1, substr_count($out, '<html'));
        }
        $this->assertStringContainsString('&lt;Acme&gt;', $this->html(fn() => $c->lista()));
    }
}
