-- Notifiche in-app (alert di scadenza). MySQL / MariaDB, InnoDB, utf8mb4.

CREATE TABLE notifiche (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    utente_id INT UNSIGNED NOT NULL,
    testo VARCHAR(500) NOT NULL,
    dominio_id INT UNSIGNED NULL,
    letta TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_notifiche_utente (utente_id, letta),
    CONSTRAINT fk_notifiche_utente FOREIGN KEY (utente_id) REFERENCES utenti (id) ON DELETE CASCADE,
    CONSTRAINT fk_notifiche_dominio FOREIGN KEY (dominio_id) REFERENCES domini (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
