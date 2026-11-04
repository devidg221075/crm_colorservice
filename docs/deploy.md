# Installazione su hosting condiviso

Guida passo passo per mettere online il gestionale domini. Servono: PHP 8.2 o superiore con estensione PDO MySQL, un database MySQL/MariaDB, Apache con `mod_rewrite`, accesso SSH o un pannello con "Cron job".

## 1. Caricare i file

1. Sul tuo computer, nella cartella del progetto, esegui `composer install --no-dev --optimize-autoloader` (crea la cartella `vendor/`). Se l'hosting ha Composer puoi farlo direttamente sul server.
2. Carica l'intera cartella del progetto (compresa `vendor/`) sul server via FTP/SFTP, ad esempio in `/home/utente/gestionale/`.
3. Non caricare `config/local.php` da altre macchine: lo creerai sul server al punto 4.

## 2. Impostare la webroot su `public/`

Nel pannello dell'hosting (sezione "Domini" o "Document root") imposta la cartella radice del sito su `.../gestionale/public`. Solo `public/` deve essere raggiungibile dal web: `app/`, `config/`, `migrations/`, `cron/` e `vendor/` devono restare fuori dalla webroot.

Il file `public/.htaccess` invia a `index.php` tutte le richieste che non sono file esistenti (serve `mod_rewrite`, di norma gia' attivo).

## 3. Creare il database e applicare le migrazioni

1. Dal pannello crea un database e un utente con tutti i privilegi su quel database. Annota nome, utente e password.
2. Applica le migrazioni **in ordine** (da phpMyAdmin con "Importa", oppure da shell):

   ```
   mysql -u UTENTE -p NOME_DB < migrations/001_init.sql
   mysql -u UTENTE -p NOME_DB < migrations/002_notifiche.sql
   ```

   Se in futuro compaiono altri file (`003_...`, ...), applicali dopo, sempre in ordine numerico. Non modificare mai una migrazione gia' applicata.

## 4. Configurazione

Copia `config/local.example.php` in `config/local.php` e inserisci le credenziali reali:

```php
return [
    'db' => [
        'dsn' => 'mysql:host=localhost;dbname=NOME_DB;charset=utf8mb4',
        'user' => 'UTENTE_DB',
        'pass' => 'PASSWORD_DB',
    ],
];
```

Opzioni facoltative (stessa struttura, sotto la chiave `app`): `nome`, `timezone` (default `Europe/Rome`), `cookie_secure` (vedi "Sessione e HTTPS"). Mittente delle email di alert: `mail.from`.

`config/local.php` non e' versionato: tienilo fuori da repository e backup pubblici.

## 5. Creare il primo amministratore

Da SSH, nella cartella del progetto:

```
php cron/crea_admin.php "Nome Cognome" tua@email.it
```

Lo script chiede la password a terminale (minimo 12 caratteri; non resta nella cronologia dei comandi). Se l'email esiste gia', l'utente viene promosso ad admin e la password aggiornata. Lo script rifiuta di girare via web.

Senza SSH: genera l'hash in locale con `php -r 'echo password_hash("LA_TUA_PASSWORD", PASSWORD_DEFAULT), "\n";'` e inseriscilo da phpMyAdmin:

```sql
INSERT INTO utenti (nome, email, password_hash, ruolo, attivo)
VALUES ('Nome Cognome', 'tua@email.it', 'HASH_GENERATO', 'admin', 1);
```

Poi accedi da `/login`. Gli altri utenti (ruolo `collaboratore`) si aggiungono nella stessa tabella `utenti`.

## 6. Cron giornaliero per gli alert di scadenza

Nel pannello "Cron job" (o con `crontab -e`) imposta una esecuzione al giorno, ad esempio alle 07:30:

```
30 7 * * * php /percorso/assoluto/gestionale/cron/alert.php
```

Il cron usa il fuso `Europe/Rome`, come l'applicazione web. Prova a mano con `php cron/alert.php`: deve stampare `Alert inviati: N`.

## 7. Backup

Il backup del database e dei file e' a carico dell'hosting: verifica nel pannello che sia attivo e che includa il database.

## Note di sicurezza e verifica

### Sessione e HTTPS (proxy TLS)

Il cookie di sessione e' marcato `secure` solo se `$_SERVER['HTTPS']` e' valorizzato. Se il sito sta dietro un proxy che termina il TLS (CDN, reverse proxy dell'hosting), PHP puo' ricevere la richiesta in HTTP semplice, `HTTPS` resta vuoto e il cookie **non** sarebbe `secure`. In quel caso imposta in `config/local.php`:

```php
'app' => ['cookie_secure' => true],
```

Con questa opzione il cookie e' sempre `secure`: usala solo se il sito e' servito esclusivamente in HTTPS.

### Bootstrap da CDN con controllo di integrita'

`views/layout.php` carica il CSS di Bootstrap 5.3.3 da jsDelivr con `integrity="sha384-..."` e `crossorigin="anonymous"`. L'hash e' stato calcolato scaricando `bootstrap@5.3.3/dist/css/bootstrap.min.css` e coincide con quello pubblicato dal progetto Bootstrap. Il layout non carica JavaScript di Bootstrap. Se in futuro si aggiorna la versione o si aggiunge il JS, l'hash va ricalcolato (`openssl dgst -sha384 -binary FILE | openssl base64 -A`, prefisso `sha384-`); non copiarlo da fonti non verificate.

### Verifica dello schema su MariaDB

Le migrazioni 001 e 002 sono state applicate su MariaDB 10.11 (database di prova, poi eliminato). Verificato: esistono tutte le 7 tabelle (`clienti`, `domini`, `titolari`, `utenti`, `alert_inviati`, `import_log`, `notifiche`); `domini.updated_at` si aggiorna da solo a ogni UPDATE (quindi non serve una migrazione 003); la cancellazione di un cliente porta a `NULL` il `cliente_id` dei suoi domini; la cancellazione di un dominio elimina a cascata titolari e alert inviati e porta a `NULL` `notifiche.dominio_id`; la cancellazione di un utente elimina le sue notifiche e porta a `NULL` `import_log.utente_id`; `LOWER(email) = ?` trova `Mario@Example.IT` con `mario@example.it`. Non verificato: MySQL vero (solo MariaDB) e un import reale di export Aruba/Serverplan, per cui servono i file reali.
