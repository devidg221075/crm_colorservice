<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Db;
use App\Repos\DominioRepo;
use App\Services\AlertService;
use App\Services\Mailer;
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;

final class FakeMailer extends Mailer
{
    /** @var list<array{string,string,string}> */
    public array $inviate = [];

    public function __construct(private bool $fallisce = false)
    {
    }

    public function invia(string $a, string $oggetto, string $corpo): bool
    {
        if ($this->fallisce) {
            return false;
        }
        $this->inviate[] = [$a, $oggetto, $corpo];
        return true;
    }
}

final class AlertServiceTest extends TestCase
{
    private AlertService $service;
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->pdo->exec('CREATE TABLE clienti (id INTEGER PRIMARY KEY AUTOINCREMENT, ragione_sociale TEXT NOT NULL,
            note TEXT NULL, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');
        $this->pdo->exec("CREATE TABLE domini (id INTEGER PRIMARY KEY AUTOINCREMENT, cliente_id INTEGER NULL,
            nome TEXT NOT NULL UNIQUE, provider TEXT NOT NULL DEFAULT 'altro', data_acquisto TEXT NULL,
            data_scadenza TEXT NULL, stato TEXT NOT NULL DEFAULT 'attivo', costo_acquisto REAL NULL,
            prezzo_rivendita REAL NULL, nameserver TEXT NULL, note_dns TEXT NULL, codice_auth TEXT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
        $this->pdo->exec("CREATE TABLE utenti (id INTEGER PRIMARY KEY AUTOINCREMENT, nome TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE, password_hash TEXT NOT NULL, ruolo TEXT NOT NULL DEFAULT 'collaboratore',
            attivo INTEGER NOT NULL DEFAULT 1)");
        $this->pdo->exec('CREATE TABLE alert_inviati (id INTEGER PRIMARY KEY AUTOINCREMENT, dominio_id INTEGER NOT NULL,
            soglia_giorni INTEGER NOT NULL, data_scadenza_riferimento TEXT NOT NULL,
            inviato_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE (dominio_id, soglia_giorni, data_scadenza_riferimento))');
        $this->pdo->exec('CREATE TABLE notifiche (id INTEGER PRIMARY KEY AUTOINCREMENT, utente_id INTEGER NOT NULL,
            testo TEXT NOT NULL, dominio_id INTEGER NULL, letta INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');
        Db::setPdo($this->pdo);
        $this->utente('simone@example.it', 1);
        $this->utente('anna@example.it', 1);
        $this->utente('ex@example.it', 0);
        $this->service = new AlertService();
    }

    protected function tearDown(): void
    {
        Db::setPdo(null);
    }

    private function utente(string $email, int $attivo): void
    {
        $this->pdo->prepare("INSERT INTO utenti (nome, email, password_hash, attivo) VALUES ('x', ?, 'h', ?)")
            ->execute([$email, $attivo]);
    }

    private function dominio(string $nome, string $scadenza, string $stato = 'attivo'): int
    {
        return (new DominioRepo())->save(['nome' => $nome, 'stato' => $stato, 'data_scadenza' => $scadenza]);
    }

    private function countAlertInviati(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM alert_inviati')->fetchColumn();
    }

    private function countNotifiche(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM notifiche')->fetchColumn();
    }

    public function test_soglia_inviata_una_sola_volta(): void
    {
        $this->dominio('esempio.it', '2026-12-04'); // 30 giorni dal 2026-11-04
        $mailer = new FakeMailer();
        $this->assertSame(1, $this->service->run(new DateTimeImmutable('2026-11-04'), $mailer));
        $this->assertSame(0, $this->service->run(new DateTimeImmutable('2026-11-05'), $mailer));
        // una email per utente attivo, una sola volta
        $this->assertCount(2, $mailer->inviate);
        $this->assertSame(['simone@example.it', 'anna@example.it'], array_column($mailer->inviate, 0));
    }

    public function test_notifica_per_ogni_utente_attivo(): void
    {
        $this->dominio('esempio.it', '2026-12-04');
        $this->service->run(new DateTimeImmutable('2026-11-04'), new FakeMailer());
        $this->assertSame(2, $this->countNotifiche());
        $testo = (string) $this->pdo->query('SELECT testo FROM notifiche LIMIT 1')->fetchColumn();
        $this->assertStringContainsString('esempio.it', $testo);
    }

    public function test_errore_smtp_non_registra_invio_e_riprova(): void
    {
        $this->dominio('esempio.it', '2026-12-04');
        $this->assertSame(0, $this->service->run(new DateTimeImmutable('2026-11-04'), new FakeMailer(true)));
        $this->assertSame(0, $this->countAlertInviati());
        $this->assertSame(0, $this->countNotifiche());
        $mailer = new FakeMailer();
        $this->assertSame(1, $this->service->run(new DateTimeImmutable('2026-11-05'), $mailer));
        $this->assertSame(2, $this->countAlertInviati()); // soglie 60 e 30 coperte
    }

    public function test_arrivo_a_15_giorni_invia_soglia_15(): void
    {
        $this->dominio('esempio.it', '2026-12-04');
        $mailer = new FakeMailer();
        $this->service->run(new DateTimeImmutable('2026-11-04'), $mailer);
        $this->assertSame(1, $this->service->run(new DateTimeImmutable('2026-11-19'), $mailer));
    }

    public function test_rinnovo_ricalcola_soglie(): void
    {
        $id = $this->dominio('esempio.it', '2026-12-04');
        $mailer = new FakeMailer();
        $this->service->run(new DateTimeImmutable('2026-11-04'), $mailer);
        // rinnovo: nuova scadenza a 2027-12-04, il giorno dopo non c'e' nulla da inviare
        $this->pdo->prepare('UPDATE domini SET data_scadenza = ? WHERE id = ?')->execute(['2027-12-04', $id]);
        $this->assertSame(0, $this->service->run(new DateTimeImmutable('2026-11-05'), $mailer));
        // a 60 giorni dalla nuova scadenza scatta di nuovo l'alert
        $this->assertSame(1, $this->service->run(new DateTimeImmutable('2027-10-05'), $mailer));
    }

    public function test_domini_non_attivi_o_lontani_sono_ignorati(): void
    {
        $this->dominio('scaduto.it', '2026-12-04', 'scaduto');
        $this->dominio('trasferito.it', '2026-12-04', 'trasferito');
        $this->dominio('lontano.it', '2027-06-01');
        $this->dominio('senzadata.it', '2026-12-04');
        $this->pdo->exec("UPDATE domini SET data_scadenza = NULL WHERE nome = 'senzadata.it'");
        $mailer = new FakeMailer();
        $this->assertSame(0, $this->service->run(new DateTimeImmutable('2026-11-04'), $mailer));
        $this->assertSame([], $mailer->inviate);
    }

    public function test_dominio_in_scadenza_e_considerato(): void
    {
        $this->dominio('esempio.it', '2026-11-10', 'in_scadenza'); // 6 giorni
        $this->assertSame(1, $this->service->run(new DateTimeImmutable('2026-11-04'), new FakeMailer()));
    }

    public function test_nessun_utente_attivo_non_registra(): void
    {
        $this->pdo->exec('UPDATE utenti SET attivo = 0');
        $this->dominio('esempio.it', '2026-12-04');
        $this->assertSame(0, $this->service->run(new DateTimeImmutable('2026-11-04'), new FakeMailer()));
        $this->assertSame(0, $this->countAlertInviati());
    }

    public function test_dominio_gia_scaduto_non_genera_alert(): void
    {
        $this->dominio('esempio.it', '2026-11-01');
        $this->assertSame(0, $this->service->run(new DateTimeImmutable('2026-11-04'), new FakeMailer()));
    }
}
