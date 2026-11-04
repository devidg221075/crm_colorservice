<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Config;
use App\Controllers\UtentiController;
use App\Csrf;
use App\Db;
use App\HttpException;
use App\Repos\UtenteRepo;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UtentiControllerTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        Config::load(__DIR__ . '/fixtures/cfg');
        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->pdo->exec('CREATE TABLE utenti (id INTEGER PRIMARY KEY AUTOINCREMENT, nome TEXT, email TEXT UNIQUE,
            password_hash TEXT, ruolo TEXT, attivo INTEGER NOT NULL DEFAULT 1)');
        $this->pdo->exec('CREATE TABLE notifiche (id INTEGER PRIMARY KEY AUTOINCREMENT, utente_id INTEGER NOT NULL,
            testo TEXT NOT NULL, dominio_id INTEGER NULL, letta INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');
        $ins = $this->pdo->prepare('INSERT INTO utenti (nome, email, password_hash, ruolo, attivo) VALUES (?,?,?,?,?)');
        $ins->execute(['Admin <Uno>', 'admin1@example.test', password_hash('vecchiapassword1', PASSWORD_DEFAULT), 'admin', 1]); // 1
        $ins->execute(['Collab', 'collab@example.test', password_hash('vecchiapassword2', PASSWORD_DEFAULT), 'collaboratore', 1]); // 2
        $ins->execute(['Admin Due', 'admin2@example.test', password_hash('vecchiapassword3', PASSWORD_DEFAULT), 'admin', 1]); // 3
        $ins->execute(['Spento', 'off@example.test', password_hash('vecchiapassword4', PASSWORD_DEFAULT), 'collaboratore', 0]); // 4
        Db::setPdo($this->pdo);
        $_SESSION = ['utente_id' => 1];
        $_POST = [];
        http_response_code(200);
    }

    protected function tearDown(): void
    {
        Db::setPdo(null);
        $_SESSION = [];
        $_POST = [];
        Config::load(__DIR__ . '/fixtures/cfg');
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

    private function expectHttpStatus(int $status, callable $fn): void
    {
        try {
            $this->html($fn);
            $this->fail("Atteso HttpException $status");
        } catch (HttpException $e) {
            $this->assertSame($status, $e->status);
        }
    }

    private function post(array $dati): void
    {
        $_POST = ['csrf' => Csrf::token()] + $dati;
    }

    private function riga(int $id): array
    {
        $st = $this->pdo->prepare('SELECT * FROM utenti WHERE id = ?');
        $st->execute([$id]);
        return $st->fetch();
    }

    private function valido(array $over = []): array
    {
        return $over + [
            'nome' => 'Nuovo Utente', 'email' => 'nuovo@example.test',
            'password' => 'passwordlunga123', 'ruolo' => 'collaboratore', 'attivo' => '1',
        ];
    }

    /** @return array<string, array{0: callable(UtentiController): void}> */
    public static function tutteLeRotte(): array
    {
        return [
            'lista' => [fn(UtentiController $c) => $c->lista()],
            'nuovo' => [fn(UtentiController $c) => $c->nuovo()],
            'crea' => [fn(UtentiController $c) => $c->crea()],
            'modifica' => [fn(UtentiController $c) => $c->modifica('2')],
            'aggiorna' => [fn(UtentiController $c) => $c->aggiorna('2')],
        ];
    }

    #[DataProvider('tutteLeRotte')]
    public function test_anonimo_401(callable $rotta): void
    {
        $_SESSION = [];
        $this->expectHttpStatus(401, fn() => $rotta(new UtentiController()));
    }

    #[DataProvider('tutteLeRotte')]
    public function test_collaboratore_403(callable $rotta): void
    {
        $_SESSION = ['utente_id' => 2];
        $this->expectHttpStatus(403, fn() => $rotta(new UtentiController()));
    }

    public function test_lista_mostra_attivi_e_disattivi_escapati_senza_hash(): void
    {
        $out = $this->html(fn() => (new UtentiController())->lista());
        $this->assertStringContainsString('Admin &lt;Uno&gt;', $out);
        $this->assertStringNotContainsString('<Uno>', $out);
        $this->assertStringContainsString('off@example.test', $out);
        $this->assertStringContainsString('/utenti/4/modifica', $out);
        $this->assertStringNotContainsString('$2y$', $out);
    }

    public function test_form_nuovo_ha_csrf_e_attivo_di_default(): void
    {
        $out = $this->html(fn() => (new UtentiController())->nuovo());
        $this->assertStringContainsString('name="csrf"', $out);
        $this->assertStringContainsString('action="/utenti"', $out);
        $this->assertMatchesRegularExpression('/name="attivo"[^>]*checked/', $out);
    }

    public function test_crea_utente_con_password_hash(): void
    {
        $this->post($this->valido());
        $this->html(fn() => (new UtentiController())->crea());
        $r = $this->pdo->query("SELECT * FROM utenti WHERE email = 'nuovo@example.test'")->fetch();
        $this->assertNotFalse($r);
        $this->assertSame('collaboratore', $r['ruolo']);
        $this->assertSame(1, (int) $r['attivo']);
        $this->assertNotSame('passwordlunga123', $r['password_hash']);
        $this->assertTrue(password_verify('passwordlunga123', $r['password_hash']));
    }

    public function test_email_duplicata_422_senza_errore_sql(): void
    {
        $this->post($this->valido(['email' => 'COLLAB@example.test']));
        $out = $this->html(fn() => (new UtentiController())->crea());
        $this->assertSame(422, http_response_code());
        $this->assertStringContainsString('Email gia', $out);
        $this->assertStringNotContainsString('SQLSTATE', $out);
        $this->assertSame(4, (int) $this->pdo->query('SELECT COUNT(*) FROM utenti')->fetchColumn());
    }

    public function test_password_debole_422_e_non_viene_ripetuta(): void
    {
        $this->post($this->valido(['password' => 'corta11']));
        $out = $this->html(fn() => (new UtentiController())->crea());
        $this->assertSame(422, http_response_code());
        $this->assertStringContainsString('almeno 12 caratteri', $out);
        $this->assertStringNotContainsString('corta11', $out);
        $this->assertSame(4, (int) $this->pdo->query('SELECT COUNT(*) FROM utenti')->fetchColumn());
    }

    public function test_ruolo_non_valido_422(): void
    {
        foreach (['superuser', 'Admin', ''] as $ruolo) {
            http_response_code(200);
            $this->post($this->valido(['ruolo' => $ruolo]));
            $this->html(fn() => (new UtentiController())->crea());
            $this->assertSame(422, http_response_code(), $ruolo);
        }
        $this->assertSame(4, (int) $this->pdo->query('SELECT COUNT(*) FROM utenti')->fetchColumn());
    }

    public function test_nome_email_obbligatori_formato_e_lunghezza(): void
    {
        $casi = [
            ['nome' => ' '], ['email' => ''], ['email' => 'non-una-email'],
            ['nome' => str_repeat('a', 192)],
            ['email' => str_repeat('a', 190) . '@example.test'],
        ];
        foreach ($casi as $caso) {
            http_response_code(200);
            $this->post($this->valido($caso));
            $this->html(fn() => (new UtentiController())->crea());
            $this->assertSame(422, http_response_code(), json_encode($caso));
        }
    }

    public function test_csrf_mancante_400_e_nessuna_scrittura(): void
    {
        $_POST = $this->valido();
        $out = $this->html(fn() => (new UtentiController())->crea());
        $this->assertSame(400, http_response_code());
        $this->assertStringContainsString('<form', $out);
        $this->assertSame(4, (int) $this->pdo->query('SELECT COUNT(*) FROM utenti')->fetchColumn());

        http_response_code(200);
        $_POST = $this->valido(['nome' => 'Hack']);
        $this->html(fn() => (new UtentiController())->aggiorna('2'));
        $this->assertSame(400, http_response_code());
        $this->assertSame('Collab', $this->riga(2)['nome']);
    }

    public function test_modifica_id_sconosciuto_404(): void
    {
        $this->html(fn() => (new UtentiController())->modifica('999'));
        $this->assertSame(404, http_response_code());
        http_response_code(200);
        $this->post($this->valido());
        $this->html(fn() => (new UtentiController())->aggiorna('999'));
        $this->assertSame(404, http_response_code());
    }

    public function test_modifica_password_vuota_mantiene_hash(): void
    {
        $prima = $this->riga(2)['password_hash'];
        $this->post($this->valido(['nome' => 'Collab Nuovo', 'email' => 'collab@example.test', 'password' => '', 'ruolo' => 'collaboratore']));
        $this->html(fn() => (new UtentiController())->aggiorna('2'));
        $r = $this->riga(2);
        $this->assertSame('Collab Nuovo', $r['nome']);
        $this->assertSame($prima, $r['password_hash']);
    }

    public function test_modifica_con_password_la_reimposta_e_minimo_12(): void
    {
        $this->post($this->valido(['email' => 'collab@example.test', 'password' => 'breve']));
        $this->html(fn() => (new UtentiController())->aggiorna('2'));
        $this->assertSame(422, http_response_code());
        $this->assertTrue(password_verify('vecchiapassword2', $this->riga(2)['password_hash']));

        http_response_code(200);
        $this->post($this->valido(['email' => 'collab@example.test', 'password' => 'nuovapassword99']));
        $this->html(fn() => (new UtentiController())->aggiorna('2'));
        $this->assertTrue(password_verify('nuovapassword99', $this->riga(2)['password_hash']));
    }

    public function test_modifica_email_duplicata_di_altro_utente_422_ma_propria_ok(): void
    {
        $this->post($this->valido(['email' => 'admin2@example.test', 'password' => '']));
        $this->html(fn() => (new UtentiController())->aggiorna('2'));
        $this->assertSame(422, http_response_code());

        http_response_code(200);
        $this->post($this->valido(['email' => 'collab@example.test', 'password' => '']));
        $this->html(fn() => (new UtentiController())->aggiorna('2'));
        $this->assertSame(302, http_response_code());
    }

    public function test_admin_non_puo_disattivare_se_stesso(): void
    {
        $this->post($this->valido(['nome' => 'Admin', 'email' => 'admin1@example.test', 'password' => '', 'ruolo' => 'admin', 'attivo' => '']));
        $out = $this->html(fn() => (new UtentiController())->aggiorna('1'));
        $this->assertSame(422, http_response_code());
        $this->assertStringContainsString('stess', $out);
        $this->assertSame(1, (int) $this->riga(1)['attivo']);
    }

    public function test_admin_non_puo_declassare_se_stesso(): void
    {
        $this->post($this->valido(['nome' => 'Admin', 'email' => 'admin1@example.test', 'password' => '', 'ruolo' => 'collaboratore', 'attivo' => '1']));
        $this->html(fn() => (new UtentiController())->aggiorna('1'));
        $this->assertSame(422, http_response_code());
        $this->assertSame('admin', $this->riga(1)['ruolo']);
    }

    public function test_admin_puo_disattivare_altro_admin_se_resta_un_admin(): void
    {
        $this->post($this->valido(['nome' => 'Admin Due', 'email' => 'admin2@example.test', 'password' => '', 'ruolo' => 'admin', 'attivo' => '']));
        $this->html(fn() => (new UtentiController())->aggiorna('3'));
        $this->assertSame(302, http_response_code());
        $this->assertSame(0, (int) $this->riga(3)['attivo']);
    }

    public function test_guardia_ultimo_admin_attivo_nel_repo(): void
    {
        $repo = new UtenteRepo();
        $this->pdo->exec('UPDATE utenti SET attivo = 0 WHERE id = 1'); // resta solo l'admin 3
        foreach ([['ruolo' => 'collaboratore', 'attivo' => 1], ['ruolo' => 'admin', 'attivo' => 0]] as $mod) {
            try {
                $repo->aggiorna(3, ['nome' => 'Admin Due', 'email' => 'admin2@example.test'] + $mod, null);
                $this->fail('Atteso rifiuto');
            } catch (\DomainException $e) {
                $this->assertStringContainsString('amministratore', $e->getMessage());
            }
        }
        $r = $this->riga(3);
        $this->assertSame('admin', $r['ruolo']);
        $this->assertSame(1, (int) $r['attivo']);
    }

    public function test_guardia_ultimo_admin_via_controller_422(): void
    {
        // Sessione di un admin che non e' piu' attivo non passa requireRole: simulo la race
        // disattivando l'unico altro admin dopo il controllo di sessione, tramite repo diretto.
        $this->pdo->exec('UPDATE utenti SET attivo = 0 WHERE id = 1');
        $_SESSION = ['utente_id' => 3];
        $this->post($this->valido(['nome' => 'Admin Due', 'email' => 'admin2@example.test', 'password' => '', 'ruolo' => 'admin', 'attivo' => '']));
        $this->html(fn() => (new UtentiController())->aggiorna('3'));
        $this->assertSame(422, http_response_code());
        $this->assertSame(1, (int) $this->riga(3)['attivo']);
    }

    public function test_link_utenti_nel_menu_solo_per_admin(): void
    {
        $out = $this->html(fn() => (new UtentiController())->lista());
        $this->assertStringContainsString('href="/utenti"', $out);

        $_SESSION = ['utente_id' => 2];
        $out = $this->html(fn() => \App\View::render('clienti/lista', ['clienti' => []]));
        $this->assertStringNotContainsString('href="/utenti"', $out);
        $this->assertStringContainsString('href="/clienti"', $out);
    }
}
