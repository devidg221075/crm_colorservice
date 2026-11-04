<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Db;
use App\Services\CsvImporter;
use PDO;
use PHPUnit\Framework\TestCase;

final class CsvImporterTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
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
    }

    private function fx(string $f): string
    {
        return __DIR__ . '/../fixtures/' . $f;
    }

    public function test_riga_con_data_italiana_e_vuota(): void
    {
        $p = CsvImporter::parse($this->fx('aruba_sample.csv'), 'aruba');
        $this->assertCount(1, $p->errori);          // la riga con data vuota
        $this->assertArrayHasKey(4, $p->errori);    // numero di riga del file (intestazione = 1)
        $this->assertSame('esempio.it', $p->nuovi[0]['nome']);
        $this->assertSame('2026-12-31', $p->nuovi[0]['data_scadenza']); // 31/12/2026 letta correttamente
        $this->assertSame('altro.com', $p->nuovi[1]['nome']);           // normalizzato
        $this->assertSame('2025-06-30', $p->nuovi[1]['data_scadenza']); // formato ISO
    }

    public function test_righe_errate_non_bloccano_le_altre(): void
    {
        $p = CsvImporter::parse($this->fx('serverplan_sample.csv'), 'serverplan');
        $this->assertSame([3, 4], array_keys($p->errori)); // nome mancante, data 31/02 inesistente
        $this->assertSame(['server.it', 'solo-scadenza.eu'], array_column($p->nuovi, 'nome'));
        $this->assertNull($p->nuovi[1]['data_acquisto']);
    }

    public function test_reimport_aggiorna_senza_duplicare(): void
    {
        $p1 = CsvImporter::parse($this->fx('aruba_sample.csv'), 'aruba');
        $this->assertSame(['ok' => 2, 'errore' => 1], CsvImporter::applica($p1, 1));
        $p2 = CsvImporter::parse($this->fx('aruba_sample.csv'), 'aruba');
        $this->assertCount(0, $p2->nuovi);
        $this->assertCount(2, $p2->aggiornati);
        CsvImporter::applica($p2, 1);
        $this->assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM domini')->fetchColumn());
        $this->assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM import_log')->fetchColumn());
    }

    public function test_applica_registra_log_e_non_tocca_costi_o_cliente(): void
    {
        $this->pdo->exec("INSERT INTO domini (nome, provider, stato, cliente_id, costo_acquisto, prezzo_rivendita, data_scadenza)
            VALUES ('esempio.it', 'altro', 'sospeso', 5, 7.5, 12, '2020-01-01')");
        $p = CsvImporter::parse($this->fx('aruba_sample.csv'), 'aruba');
        CsvImporter::applica($p, 1);
        $r = $this->pdo->query("SELECT * FROM domini WHERE nome = 'esempio.it'")->fetch();
        $this->assertSame('2026-12-31', $r['data_scadenza']);
        $this->assertSame('aruba', $r['provider']);
        $this->assertSame('sospeso', $r['stato']);
        $this->assertSame(5, (int) $r['cliente_id']);
        $this->assertEquals(7.5, $r['costo_acquisto']);
        $this->assertEquals(12, $r['prezzo_rivendita']);
        $log = $this->pdo->query('SELECT * FROM import_log')->fetch();
        $this->assertSame('import', $log['tipo']);
        $this->assertSame('aruba_sample.csv', $log['file']);
        $this->assertSame(2, (int) $log['righe_ok']);
        $this->assertSame(1, (int) $log['righe_errore']);
    }

    public function test_colonna_obbligatoria_mancante(): void
    {
        $f = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($f, "dominio;scade\nx.it;01/01/2027\n");
        $p = CsvImporter::parse($f, 'aruba');
        unlink($f);
        $this->assertSame([], $p->nuovi);
        $this->assertArrayHasKey(1, $p->errori);
    }
}
