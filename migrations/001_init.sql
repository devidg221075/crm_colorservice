-- Schema iniziale. MySQL / MariaDB, InnoDB, utf8mb4.

CREATE TABLE clienti (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    ragione_sociale VARCHAR(255) NOT NULL,
    note TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE domini (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT UNSIGNED NULL,
    nome VARCHAR(253) NOT NULL,
    provider ENUM('aruba','serverplan','altro') NOT NULL DEFAULT 'altro',
    data_acquisto DATE NULL,
    data_scadenza DATE NULL,
    stato VARCHAR(40) NOT NULL DEFAULT 'attivo',
    costo_acquisto DECIMAL(10,2) NULL,
    prezzo_rivendita DECIMAL(10,2) NULL,
    nameserver TEXT NULL,
    note_dns TEXT NULL,
    codice_auth TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_domini_nome (nome),
    KEY idx_domini_cliente (cliente_id),
    KEY idx_domini_scadenza (data_scadenza),
    CONSTRAINT fk_domini_cliente FOREIGN KEY (cliente_id) REFERENCES clienti (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE titolari (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    dominio_id INT UNSIGNED NOT NULL,
    ragione_sociale VARCHAR(255) NULL,
    cf_piva VARCHAR(32) NULL,
    indirizzo VARCHAR(255) NULL,
    pec VARCHAR(255) NULL,
    email VARCHAR(255) NULL,
    telefono VARCHAR(50) NULL,
    KEY idx_titolari_dominio (dominio_id),
    CONSTRAINT fk_titolari_dominio FOREIGN KEY (dominio_id) REFERENCES domini (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE utenti (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(120) NOT NULL,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    ruolo ENUM('admin','collaboratore') NOT NULL DEFAULT 'collaboratore',
    attivo TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uq_utenti_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE alert_inviati (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    dominio_id INT UNSIGNED NOT NULL,
    soglia_giorni INT NOT NULL,
    data_scadenza_riferimento DATE NOT NULL,
    inviato_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_alert (dominio_id, soglia_giorni, data_scadenza_riferimento),
    CONSTRAINT fk_alert_dominio FOREIGN KEY (dominio_id) REFERENCES domini (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE import_log (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    utente_id INT UNSIGNED NULL,
    tipo VARCHAR(40) NOT NULL DEFAULT 'import',
    file VARCHAR(255) NOT NULL,
    data DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    righe_ok INT UNSIGNED NOT NULL DEFAULT 0,
    righe_errore INT UNSIGNED NOT NULL DEFAULT 0,
    CONSTRAINT fk_import_utente FOREIGN KEY (utente_id) REFERENCES utenti (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
