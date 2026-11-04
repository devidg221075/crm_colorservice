<?php
declare(strict_types=1);

// Crea (o promuove) un utente admin. Solo da riga di comando:
//   php cron/crea_admin.php "Nome Cognome" email@esempio.it
// La password viene chiesta a terminale (non finisce nella cronologia dei comandi).
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Db;

if ($argc !== 3) {
    fwrite(STDERR, "Uso: php cron/crea_admin.php \"Nome\" email\n");
    exit(1);
}
[$nome, $email] = [trim($argv[1]), trim($argv[2])];
if ($nome === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Nome o email non validi.\n");
    exit(1);
}

$nascondi = stripos(PHP_OS, 'WIN') !== 0 && stream_isatty(STDIN);
echo 'Password (min. 12 caratteri): ';
if ($nascondi) {
    system('stty -echo');
}
$pw = trim((string) fgets(STDIN));
if ($nascondi) {
    system('stty echo');
}
echo "\n";
if (strlen($pw) < 12) {
    fwrite(STDERR, "Password troppo corta.\n");
    exit(1);
}

try {
    $st = Db::pdo()->prepare(
        'INSERT INTO utenti (nome, email, password_hash, ruolo, attivo) VALUES (?, ?, ?, \'admin\', 1)
         ON DUPLICATE KEY UPDATE nome = VALUES(nome), password_hash = VALUES(password_hash), ruolo = \'admin\', attivo = 1'
    );
    $st->execute([$nome, $email, password_hash($pw, PASSWORD_DEFAULT)]);
    echo "Admin pronto: $email\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Errore: ' . $e->getMessage() . "\n");
    exit(1);
}
