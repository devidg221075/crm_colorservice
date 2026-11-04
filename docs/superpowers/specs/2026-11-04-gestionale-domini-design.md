# Gestionale domini — Color Service (design v1)

Data: 2026-11-04 · Stato: in attesa di revisione

## 1. Obiettivo

Un gestionale interno per Simone (studio grafico Color Service) per tracciare i domini che lo studio gestisce per conto dei propri clienti, registrati presso **Aruba** e **Serverplan**. Deve ricordare le scadenze, mostrare costi e margini, e conservare i dati anagrafici del titolare e i codici di trasferimento.

Destinatari: Simone e i collaboratori dello studio. Nessun accesso per i clienti in questa versione.

## 2. Ambito della prima versione

**Dentro:**
- Anagrafica clienti e domini collegati ai clienti.
- Import da file CSV/Excel esportati da Aruba e Serverplan, con anteprima prima della conferma.
- Inserimento e modifica manuale di ogni dominio.
- Stati: Attivo, In scadenza, Scaduto / da rinnovare, Trasferito / disdetto. Aggiornamento manuale.
- Costo d'acquisto, prezzo di rivendita, margine calcolato.
- Alert di scadenza via email e notifica nell'app, a 60, 30, 15 e 7 giorni prima della scadenza.
- Dati del titolare: ragione sociale, CF/P.IVA, indirizzo, PEC, email, telefono.
- Codici Auth-Info / EPP, visibili dopo il login.
- Note testuali su nameserver e record DNS, inseriti a mano.
- Utenti multipli con ruoli (vedi sezione 5).
- Interfaccia solo in italiano.

**Fuori dalla prima versione:** integrazione API con i provider, modifica dei DNS dal gestionale, WhatsApp, area clienti, lingua inglese, rinnovo automatico, lettura automatica dello stato dei domini.

## 3. Architettura

- **Linguaggio:** PHP 8.x, senza framework pesante. Router minimale, template HTML.
- **Interfaccia:** HTML server-side con Bootstrap. Nessuna SPA.
- **Database:** MySQL / MariaDB, con migrazioni versionate in SQL.
- **Hosting:** condiviso economico con PHP e MySQL. Nessun processo sempre attivo.
- **Cron giornaliero** (cron dell'hosting) che lancia lo script di controllo scadenze e invio alert.
- **Email:** SMTP dell'hosting.
- **Configurazione:** file fuori dalla webroot per credenziali database e SMTP.

Struttura indicativa:
```
app/        controller, modelli, servizi (import, alert, margini)
views/      template HTML
public/     unica cartella esposta (index.php, asset)
cron/       script giornaliero alert
migrations/ file SQL numerati
tests/      test unitari su import, soglie, margini
config/     esempio di configurazione (senza segreti)
```

## 4. Modello dati

- **clienti**: id, ragione sociale, note, created_at.
- **domini**: id, cliente_id, nome (unico, normalizzato in minuscolo), provider (`aruba` | `serverplan` | `altro`), data_acquisto, data_scadenza, stato, costo_acquisto, prezzo_rivendita, nameserver (testo), note_dns (testo), codice_auth (testo), created_at, updated_at.
- **titolari**: id, dominio_id, ragione_sociale, cf_piva, indirizzo, pec, email, telefono.
- **utenti**: id, nome, email, password_hash, ruolo (`admin` | `collaboratore`), attivo.
- **alert_inviati**: id, dominio_id, soglia_giorni, data_scadenza_riferimento, inviato_at. Chiave unica su (dominio_id, soglia_giorni, data_scadenza_riferimento) per non inviare due volte lo stesso avviso.
- **import_log**: id, utente_id, file, data, righe_ok, righe_errore.

Il margine non è memorizzato: è calcolato come `prezzo_rivendita - costo_acquisto`.

## 5. Ruoli e permessi

- **Admin (Simone):** accesso completo, gestione utenti, import, configurazione alert.
- **Collaboratore:** vede domini, clienti, scadenze, codici EPP e dati del titolare. Non vede costi d'acquisto, prezzi di rivendita e margini. Può creare e modificare clienti e domini, ma non può gestire utenti né configurazione.

Costi e margini sono visibili solo agli admin: vanno esclusi da liste, export CSV, dettagli e API interne verso i ruoli collaboratore.

## 6. Flussi principali

**Import CSV/Excel**
1. Caricamento del file e scelta del provider.
2. Mappatura automatica delle colonne, con possibilità di correggerla.
3. Anteprima: nuovi domini, domini aggiornati, righe con errori.
4. Conferma. Le righe valide vengono salvate; quelle con errori vengono segnalate senza bloccare le altre.
5. Il dominio è riconosciuto dal nome: un re-import aggiorna senza duplicare.

**Alert scadenza**
1. Ogni giorno il cron calcola i giorni mancanti per ogni dominio attivo o in scadenza.
2. Per ogni soglia raggiunta (60, 30, 15, 7) che non risulta in `alert_inviati`, invia un'email a tutti gli utenti attivi e crea una notifica nell'app per ciascuno.
3. Registra l'invio in `alert_inviati`.
4. Se la scadenza cambia (rinnovo), le soglie vengono ricalcolate.

**Aggiornamento stato**
Manuale dalla scheda del dominio. Il sistema non modifica lo stato da solo.

## 7. Errori e test

- Righe CSV non valide: segnalate nell'anteprima, non bloccano l'import.
- Errore SMTP: l'alert non viene registrato come inviato, quindi il cron riprova il giorno dopo.
- Test unitari: parsing CSV, calcolo soglie, calcolo margine, normalizzazione dei nomi di dominio.
- Test manuale su un export reale di Aruba e uno di Serverplan prima del rilascio.
- Backup del database gestito dall'hosting, con verifica periodica del ripristino.

## 8. Sicurezza e privacy

- Password con `password_hash` (bcrypt/argon2), sessioni HTTP-only, protezione CSRF su ogni form.
- Codici EPP e dati del titolare visibili solo dopo login. Rischio noto: in caso di uscita di un collaboratore, i codici restano leggibili fino al cambio di password. Mitigazione proposta: procedura di disattivazione utente che ricorda di ruotare i codici presso il provider.
- Dati personali del titolare: informativa da redigere; funzione di esportazione e cancellazione dei dati di una persona.
- Log degli accessi ai codici EPP.

## 9. Decisioni prese in revisione

- Costi e margini: solo admin.
- Codici EPP e dati del titolare: visibili ai collaboratori.
- Nessun promemoria di rotazione EPP alla disattivazione utente; la gestione resta manuale.
- Alert: inviati a tutti gli utenti attivi.
