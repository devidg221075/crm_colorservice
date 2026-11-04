# Gestionale domini Color Service — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Gestionale PHP per tracciare i domini dei clienti di Color Service registrati presso Aruba e Serverplan: anagrafica, import da file, scadenze con alert email e in-app, costi e margini visibili solo agli admin.

**Architecture:** Applicazione PHP 8 senza framework, router minimo, template HTML server-side, MySQL/MariaDB con migrazioni SQL numerate. Un cron giornaliero lancia il servizio alert. Nessuna API verso i provider.

**Tech Stack:** PHP 8.2+, PDO (MySQL), PHPUnit 10, Bootstrap 5 (CDN), PhpSpreadsheet solo per gli Excel, SMTP dell'hosting via `mail()` o SMTP semplice.

**Spec:** `docs/superpowers/specs/2026-11-04-gestionale-domini-design.md`

## Global Constraints

- Interfaccia solo in italiano.
- Nessun framework PHP; dipendenze Composer limitate a PHPUnit (dev), PhpSpreadsheet, e un client SMTP se `mail()` non è disponibile.
- Stati dominio esatti: `attivo`, `in_scadenza`, `scaduto`, `trasferito`.
- Provider esatti: `aruba`, `serverplan`, `altro`.
- Ruoli esatti: `admin`, `collaboratore`.
- Soglie alert esatte: 60, 30, 15, 7 giorni.
- Costi (`costo_acquisto`, `prezzo_rivendita`) e margine visibili solo al ruolo `admin`.
- Codici EPP e dati del titolare visibili a entrambi i ruoli.
- Credenziali DB e SMTP fuori dalla webroot (`config/local.php`, non versionato).
- Ogni form POST protetto da token CSRF.
- Password con `password_hash` / `password_verify`.

## Review Focus

1. Nome dominio con maiuscole, spazi o punto finale (es. `Esempio.IT.`): deve essere normalizzato e riconosciuto come lo stesso dominio al re-import.
2. Riga CSV con data in formato italiano (`31/12/2026`) o con cella vuota: non deve far fallire l'intero import.
3. Rinnovo che sposta la scadenza in avanti: gli alert già inviati per la vecchia scadenza non devono bloccare quelli della nuova.
4. Errore SMTP durante il cron: l'alert non va registrato come inviato, e il giorno dopo deve essere ritentato.
5. Collaboratore che chiama direttamente una rotta di costi (es. `/domini/1/margine`): deve ricevere 403, non i dati.

---

## File Structure

- `public/index.php` — unico entry point, carica il router.
- `app/Config.php` — legge `config/app.php` e `config/local.php`.
- `app/Db.php` — crea la connessione PDO singleton.
- `app/Router.php` — mappa metodo+path a handler.
- `app/Auth.php` — login, logout, sessione, `requireRole()`.
- `app/Csrf.php` — genera e verifica il token.
- `app/Repos/ClienteRepo.php`, `app/Repos/DominioRepo.php`, `app/Repos/TitolareRepo.php`, `app/Repos/UtenteRepo.php`, `app/Repos/NotificaRepo.php` — accesso dati.
- `app/Services/Margine.php` — calcolo margine puro.
- `app/Services/Stato.php` — calcolo giorni alla scadenza e stato suggerito (puro).
- `app/Services/CsvImporter.php` — parsing e anteprima import.
- `app/Services/AlertService.php` — logica alert giornaliera.
- `app/Services/Mailer.php` — invio email, interfaccia sostituibile in test.
- `app/Controllers/*.php` — una classe per area (Auth, Clienti, Domini, Import, Utenti, Privacy).
- `views/*.php` — template.
- `migrations/001_init.sql`, `002_notifiche.sql`.
- `cron/alert.php` — entry point del cron.
- `tests/unit/*.php`, `tests/fixtures/aruba_sample.csv`, `tests/fixtures/serverplan_sample.csv`.

---

### Task 1: Scaffold, configurazione e migrazioni

**Files:**
- Create: `public/index.php`, `app/Config.php`, `app/Db.php`, `app/Router.php`, `config/app.php`, `config/local.example.php`, `migrations/001_init.sql`, `composer.json`, `phpunit.xml`, `tests/unit/ConfigTest.php`

**Interfaces:**
- Produces: `App\Config::get(string $key, mixed $default = null): mixed`; `App\Db::pdo(): PDO`; `App\Router::add(string $method, string $path, callable $handler): void`; `App\Router::dispatch(string $method, string $uri): void`.

- [ ] **Step 1: Scrivi il test di configurazione**

```php
public function test_local_overrides_app(): void {
    Config::load(__DIR__.'/fixtures/cfg');
    $this->assertSame('mysql', Config::get('db.driver'));
    $this->assertSame('override', Config::get('app.nome'));
}
```

- [ ] **Step 2: Esegui e verifica che fallisca**

Run: `vendor/bin/phpunit tests/unit/ConfigTest.php`
Expected: FAIL, classe `App\Config` non definita.

- [ ] **Step 3: Implementa `Config`, `Db`, `Router` e `public/index.php`**

`Config::load()` unisce `config/app.php` con `config/local.php` (se presente). `Db::pdo()` usa `db.dsn`, `db.user`, `db.pass` con `ERRMODE_EXCEPTION` e `FETCH_ASSOC`. Il router risponde 404 per le rotte sconosciute.

- [ ] **Step 4: Scrivi `migrations/001_init.sql`**

Tabelle `clienti`, `domini`, `titolari`, `utenti`, `alert_inviati`, `import_log` con i campi della specifica, sezione 4. Chiavi: `domini.nome` UNIQUE; `alert_inviati` UNIQUE su `(dominio_id, soglia_giorni, data_scadenza_riferimento)`; `titolari.dominio_id` FK con `ON DELETE CASCADE`.

- [ ] **Step 5: Esegui i test e applica la migrazione su un DB di prova**

Run: `vendor/bin/phpunit` → PASS.
Run: `mysql -u root -p gestionale_test < migrations/001_init.sql` → nessun errore; `SHOW TABLES` mostra 6 tabelle.

- [ ] **Step 6: Commit**

```bash
git add -A && git commit -m "feat: scaffold app, config and initial schema"
```

---

### Task 2: Autenticazione e ruoli

**Files:**
- Create: `app/Auth.php`, `app/Csrf.php`, `app/Repos/UtenteRepo.php`, `app/Controllers/AuthController.php`, `views/login.php`, `tests/unit/AuthTest.php`

**Interfaces:**
- Consumes: `Db::pdo()`
- Produces: `Auth::attempt(string $email, string $password): bool`; `Auth::user(): ?array` (con chiavi `id`, `nome`, `ruolo`); `Auth::requireLogin(): void`; `Auth::requireRole(string $ruolo): void` (403 se non corrisponde); `Csrf::token(): string`; `Csrf::check(string $token): bool`.

- [ ] **Step 1: Scrivi i test**

```php
public function test_collaboratore_non_passa_requireRole_admin(): void {
    $this->loginAs('collaboratore');
    $this->expectHttpStatus(403, fn() => Auth::requireRole('admin'));
}
public function test_password_errata_rifiutata(): void {
    $this->assertFalse(Auth::attempt('simone@example.test', 'sbagliata'));
}
```

- [ ] **Step 2: Esegui e verifica che falliscano**

Run: `vendor/bin/phpunit tests/unit/AuthTest.php` → FAIL.

- [ ] **Step 3: Implementa `Auth`, `Csrf`, `UtenteRepo` e il controller di login**

Sessione con `session_regenerate_id(true)` al login. Cookie `httponly`, `samesite=Lax`. Utenti disattivati (`attivo = 0`) non possono accedere.

- [ ] **Step 4: Esegui i test**

Run: `vendor/bin/phpunit tests/unit/AuthTest.php` → PASS.

- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "feat: login, sessions, roles and CSRF"
```

---

### Task 3: Clienti

**Files:**
- Create: `app/Repos/ClienteRepo.php`, `app/Controllers/ClientiController.php`, `views/clienti/lista.php`, `views/clienti/form.php`, `tests/unit/ClienteRepoTest.php`

**Interfaces:**
- Consumes: `Db::pdo()`, `Auth::requireRole()`, `Csrf::check()`
- Produces: `ClienteRepo::all(): array`; `ClienteRepo::find(int $id): ?array`; `ClienteRepo::save(array $dati): int` (inserisce se `id` assente, altrimenti aggiorna).

- [ ] **Step 1: Scrivi i test del repository**

```php
public function test_salva_e_recupera_cliente(): void {
    $id = $this->repo->save(['ragione_sociale' => 'Rossi Srl', 'note' => '']);
    $this->assertSame('Rossi Srl', $this->repo->find($id)['ragione_sociale']);
}
```

- [ ] **Step 2: Esegui e verifica che fallisca**

Run: `vendor/bin/phpunit tests/unit/ClienteRepoTest.php` → FAIL.

- [ ] **Step 3: Implementa `ClienteRepo` e il controller con le rotte `GET /clienti`, `GET /clienti/nuovo`, `POST /clienti`, `GET /clienti/{id}/modifica`, `POST /clienti/{id}`**

Le query usano sempre parametri preparati. Validazione: `ragione_sociale` obbligatoria, massimo 191 caratteri.

- [ ] **Step 4: Esegui i test**

Run: `vendor/bin/phpunit tests/unit/ClienteRepoTest.php` → PASS.

- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "feat: customers list and form"
```

---

### Task 4: Domini, titolare, stato e margine

**Files:**
- Create: `app/Services/Margine.php`, `app/Services/Stato.php`, `app/Services/Dominio.php` (normalizzazione nome), `app/Repos/DominioRepo.php`, `app/Repos/TitolareRepo.php`, `app/Controllers/DominiController.php`, `views/domini/lista.php`, `views/domini/scheda.php`, `views/domini/form.php`, `tests/unit/MargineTest.php`, `tests/unit/StatoTest.php`, `tests/unit/DominioNomeTest.php`

**Interfaces:**
- Produces: `Margine::calcola(?float $acquisto, ?float $rivendita): ?float` (null se uno dei due manca); `Stato::giorniAllaScadenza(DateTimeImmutable $oggi, DateTimeImmutable $scadenza): int`; `Stato::suggerito(int $giorni, string $statoAttuale): string`; `NomeDominio::normalizza(string $nome): string` (minuscolo, senza spazi, senza punto finale); `DominioRepo::find(int $id): ?array`; `DominioRepo::save(array $dati): int`; `DominioRepo::lista(array $filtri): array`.

- [ ] **Step 1: Scrivi i test puri**

```php
public function test_margine_con_valori_mancanti_e_null(): void {
    $this->assertNull(Margine::calcola(null, 12.0));
    $this->assertSame(4.5, Margine::calcola(7.5, 12.0));
}
public function test_normalizza_nome(): void {
    $this->assertSame('esempio.it', NomeDominio::normalizza(' Esempio.IT. '));
}
public function test_giorni_alla_scadenza(): void {
    $this->assertSame(30, Stato::giorniAllaScadenza(new DateTimeImmutable('2026-11-04'), new DateTimeImmutable('2026-12-04')));
}
```

- [ ] **Step 2: Esegui e verifica che falliscano**

Run: `vendor/bin/phpunit tests/unit/` → FAIL sulle nuove classi.

- [ ] **Step 3: Implementa `Margine`, `Stato`, `NomeDominio`, i repository e il controller**

`Stato::suggerito`: giorni < 0 → `scaduto`; 0–60 → `in_scadenza`; altrimenti lo stato attuale, purché non sia `trasferito`. Il suggerimento è mostrato all'utente, non applicato automaticamente.

Nella lista e nella scheda: se l'utente non è admin, le colonne/campi `costo_acquisto`, `prezzo_rivendita` e margine non vengono né renderizzati né inclusi nella query (la selezione SQL dipende dal ruolo).

Rotte: `GET /domini`, `GET /domini/{id}`, `GET /domini/nuovo`, `POST /domini`, `POST /domini/{id}`.

- [ ] **Step 4: Esegui i test**

Run: `vendor/bin/phpunit tests/unit/` → PASS.

- [ ] **Step 5: Verifica manuale del ruolo**

Login come collaboratore, apri `/domini/1/margine` e la scheda: attesi 403 sulla rotta margine e nessun campo di costo nel HTML della scheda.

- [ ] **Step 6: Commit**

```bash
git add -A && git commit -m "feat: domains, holder data, status and margin with role-based fields"
```

---

### Task 5: Import CSV/Excel con anteprima

**Files:**
- Create: `app/Services/CsvImporter.php`, `app/Controllers/ImportController.php`, `views/import/anteprima.php`, `tests/unit/CsvImporterTest.php`, `tests/fixtures/aruba_sample.csv`, `tests/fixtures/serverplan_sample.csv`

**Interfaces:**
- Consumes: `NomeDominio::normalizza()`, `DominioRepo::find/save`
- Produces: `CsvImporter::parse(string $percorso, string $provider): ImportPreview` dove `ImportPreview` ha `nuovi: array`, `aggiornati: array`, `errori: array<int,string>`; `CsvImporter::applica(ImportPreview $p, int $utenteId): array{ok:int, errore:int}`.

**Dipendenza aperta:** servono due export reali (uno Aruba, uno Serverplan) per fissare il mapping delle colonne. Fino ad allora la task usa fixture costruite a mano con le colonne attese: `nome`, `data_scadenza`, `data_acquisto`. Il mapping va verificato sui file veri prima del rilascio.

- [ ] **Step 1: Scrivi i test con le fixture**

```php
public function test_riga_con_data_italiana_e_vuota(): void {
    $p = CsvImporter::parse(__DIR__.'/../fixtures/aruba_sample.csv', 'aruba');
    $this->assertCount(1, $p->errori);          // la riga con data vuota
    $this->assertSame('esempio.it', $p->nuovi[0]['nome']); // data 31/12/2026 letta correttamente
}
public function test_reimport_aggiorna_senza_duplicare(): void {
    // parse della stessa fixture due volte, applica, il secondo import va in aggiornati
}
```

- [ ] **Step 2: Esegui e verifica che falliscano**

Run: `vendor/bin/phpunit tests/unit/CsvImporterTest.php` → FAIL.

- [ ] **Step 3: Implementa `CsvImporter`**

Date accettate: `dd/mm/yyyy` e `yyyy-mm-dd`. Righe senza nome o con data non valida vanno in `errori` con numero di riga, senza bloccare le altre. Il dominio viene riconosciuto con `NomeDominio::normalizza()`. `applica()` scrive in transazione e registra una riga in `import_log`.

Rotte: `GET /import`, `POST /import` (carica file e mostra anteprima), `POST /import/conferma`.

- [ ] **Step 4: Esegui i test**

Run: `vendor/bin/phpunit tests/unit/CsvImporterTest.php` → PASS.

- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "feat: CSV import with preview and idempotent re-import"
```

---

### Task 6: Servizio alert e cron

**Files:**
- Create: `app/Services/AlertService.php`, `app/Services/Mailer.php`, `cron/alert.php`, `tests/unit/AlertServiceTest.php`

**Interfaces:**
- Consumes: `Stato::giorniAllaScadenza()`, `DominioRepo`, `UtenteRepo`, `NotificaRepo`
- Produces: `AlertService::run(DateTimeImmutable $oggi, Mailer $mailer): int` (numero di alert inviati); interfaccia `Mailer::invia(string $a, string $oggetto, string $corpo): bool`.

- [ ] **Step 1: Scrivi i test con un `Mailer` finto**

```php
public function test_soglia_inviata_una_sola_volta(): void {
    $mailer = new FakeMailer();
    $this->service->run(new DateTimeImmutable('2026-11-04'), $mailer); // dominio scade il 2026-12-04 → soglia 30
    $this->service->run(new DateTimeImmutable('2026-11-05'), $mailer);
    $this->assertCount(1, $mailer->inviate);
}
public function test_errore_smtp_non_registra_invio(): void {
    $mailer = new FakeMailer(fallisce: true);
    $this->service->run(new DateTimeImmutable('2026-11-04'), $mailer);
    $this->assertSame(0, $this->countAlertInviati());
}
public function test_rinnovo_ricalcola_soglie(): void { /* nuova scadenza → nuove soglie inviabili */ }
```

- [ ] **Step 2: Esegui e verifica che falliscano**

Run: `vendor/bin/phpunit tests/unit/AlertServiceTest.php` → FAIL.

- [ ] **Step 3: Implementa `AlertService` e `Mailer`**

Per ogni dominio in stato `attivo` o `in_scadenza`, per ogni soglia in [60, 30, 15, 7] con giorni rimanenti ≤ soglia: se non esiste la riga in `alert_inviati` con `(dominio_id, soglia, data_scadenza)`, invia a tutti gli utenti attivi, crea una notifica per ciascuno, e registra la riga solo se l'invio ha avuto successo.

`cron/alert.php`: carica la configurazione, chiama `AlertService::run(new DateTimeImmutable('today'), new Mailer())`, scrive l'esito su stdout.

- [ ] **Step 4: Esegui i test**

Run: `vendor/bin/phpunit tests/unit/AlertServiceTest.php` → PASS.

- [ ] **Step 5: Verifica del cron sull'hosting**

Run: `php cron/alert.php` → stampa il numero di alert inviati, senza errori.

- [ ] **Step 6: Commit**

```bash
git add -A && git commit -m "feat: daily expiry alerts with per-threshold deduplication"
```

---

### Task 7: Notifiche in app

**Files:**
- Create: `migrations/002_notifiche.sql`, `app/Repos/NotificaRepo.php`, `app/Controllers/NotificheController.php`, `views/partials/campanella.php`, `tests/unit/NotificaRepoTest.php`

**Interfaces:**
- Produces: `NotificaRepo::crea(int $utenteId, string $testo, ?int $dominioId): void`; `NotificaRepo::nonLette(int $utenteId): array`; `NotificaRepo::segnaLette(int $utenteId): void`.

- [ ] **Step 1: Scrivi i test del repository**

```php
public function test_segna_letto_azzera_non_lette(): void {
    $this->repo->crea(1, 'Scade fra 30 giorni', null);
    $this->repo->segnaLette(1);
    $this->assertCount(0, $this->repo->nonLette(1));
}
```

- [ ] **Step 2: Esegui e verifica che fallisca**

Run: `vendor/bin/phpunit tests/unit/NotificaRepoTest.php` → FAIL.

- [ ] **Step 3: Implementa migrazione, repository, controller e badge nel layout**

Tabella `notifiche`: id, utente_id (FK), testo, dominio_id (FK nullable), letta (bool), created_at. Il badge con il conteggio compare nel layout per ogni utente loggato.

- [ ] **Step 4: Esegui i test e verifica il badge nel browser**

Run: `vendor/bin/phpunit tests/unit/NotificaRepoTest.php` → PASS. Poi, dopo `php cron/alert.php` con un dominio in scadenza, il badge mostra il conteggio.

- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "feat: in-app notifications for expiry alerts"
```

---

### Task 8: Privacy — esportazione e cancellazione dei dati del titolare

**Files:**
- Create: `app/Controllers/PrivacyController.php`, `app/Services/PrivacyService.php`, `views/privacy/scheda.php`, `tests/unit/PrivacyServiceTest.php`

**Interfaces:**
- Consumes: `TitolareRepo`
- Produces: `PrivacyService::esporta(string $email): array` (tutti i titolari con quell'email, con i domini collegati); `PrivacyService::cancella(string $email): int` (numero di titolari anonimizzati).

- [ ] **Step 1: Scrivi i test**

```php
public function test_cancellazione_anonimizza_senza_eliminare_il_dominio(): void {
    $n = $this->service->cancella('mario@example.test');
    $this->assertSame(1, $n);
    $this->assertNotNull($this->domini->find(1)); // il dominio resta
    $this->assertNull($this->titolari->findByEmail('mario@example.test'));
}
```

- [ ] **Step 2: Esegui e verifica che fallisca**

Run: `vendor/bin/phpunit tests/unit/PrivacyServiceTest.php` → FAIL.

- [ ] **Step 3: Implementa il servizio e la scheda di ricerca per email (solo admin)**

Cancellazione: sovrascrive ragione sociale, CF/P.IVA, indirizzo, PEC, email, telefono con `NULL` e registra l'operazione in `import_log` con tipo `privacy_cancellazione`. Richiede `Auth::requireRole('admin')`.

- [ ] **Step 4: Esegui i test**

Run: `vendor/bin/phpunit tests/unit/PrivacyServiceTest.php` → PASS.

- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "feat: privacy export and erasure for holder data"
```

---

### Task 9: Rilascio su hosting condiviso

**Files:**
- Create: `README.md`, `docs/deploy.md`, `config/local.example.php` (già da Task 1, completato qui)

**Interfaces:**
- Consumes: tutte le precedenti.
- Produces: istruzioni di installazione e di configurazione del cron.

- [ ] **Step 1: Scrivi `docs/deploy.md`**

Passi: caricare la cartella, impostare la webroot su `public/`, creare il DB, eseguire le migrazioni in ordine, copiare `config/local.example.php` in `config/local.php` con le credenziali, creare il primo admin (script `cron/crea_admin.php` o SQL manuale), impostare il cron giornaliero `php /percorso/cron/alert.php`.

- [ ] **Step 2: Verifica su un'installazione di prova**

Esegui tutti i test: `vendor/bin/phpunit` → PASS.
Import di un export reale Aruba e uno Serverplan: nessun errore, anteprima corretta.
Cron di prova: `php cron/alert.php` → esito atteso.

- [ ] **Step 3: Commit**

```bash
git add -A && git commit -m "docs: deployment guide for shared hosting"
```

---

## Self-review

- **Copertura spec:** sezioni 2 (ambito) → task 3, 4, 5, 6, 7; sezione 4 (modello) → task 1; sezione 5 (ruoli) → task 2 e 4; sezione 6 (flussi) → task 5 e 6; sezione 7 (errori e test) → task 5, 6; sezione 8 (sicurezza e privacy) → task 2 (CSRF, password), task 8 (privacy). Backup: gestito dall'hosting, documentato in task 9.
- **Coerenza tipi:** `Stato`, `Margine`, `NomeDominio`, `CsvImporter`, `AlertService`, `NotificaRepo` hanno firme definite una sola volta nelle task che le producono.
- **Dipendenza aperta:** il mapping colonne CSV dipende da due export reali, da ottenere da Simone prima del task 5.
