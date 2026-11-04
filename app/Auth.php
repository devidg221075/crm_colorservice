<?php
declare(strict_types=1);

namespace App;

use App\Repos\UtenteRepo;

final class Auth
{
    public const RUOLI = ['admin', 'collaboratore'];

    /** Avvia la sessione con cookie httponly e samesite=Lax (no-op se gia' attiva). */
    public static function startSession(): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    public static function attempt(string $email, string $password): bool
    {
        $u = (new UtenteRepo())->trovaAttivoPerEmail(trim($email));
        if ($u === null || !password_verify($password, $u['password_hash'])) {
            return false;
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION['utente_id'] = (int) $u['id'];
        return true;
    }

    /** Utente corrente (id, nome, ruolo) o null; rilegge il DB cosi' disattivazioni e cambi ruolo valgono subito. */
    public static function user(): ?array
    {
        $id = $_SESSION['utente_id'] ?? null;
        if (!is_int($id)) {
            return null;
        }
        $u = (new UtenteRepo())->trovaAttivoPerId($id);
        if ($u === null) {
            return null;
        }
        return ['id' => (int) $u['id'], 'nome' => $u['nome'], 'ruolo' => $u['ruolo']];
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    /** Lancia HttpException 401 se non autenticato (il Router reindirizza a /login). */
    public static function requireLogin(): void
    {
        if (self::user() === null) {
            throw new HttpException(401, 'Accesso richiesto');
        }
    }

    /** Lancia HttpException 403 se il ruolo non corrisponde. */
    public static function requireRole(string $ruolo): void
    {
        self::requireLogin();
        if (self::user()['ruolo'] !== $ruolo) {
            throw new HttpException(403, 'Accesso negato');
        }
    }
}
