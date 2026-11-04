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
            'secure' => self::cookieSecure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_strict_mode', '1');
        session_start();
    }

    /** Cookie 'secure' se la richiesta e' HTTPS, o se forzato da config (app.cookie_secure) dietro proxy TLS. */
    public static function cookieSecure(): bool
    {
        return !empty($_SERVER['HTTPS']) || Config::get('app.cookie_secure', false) === true;
    }

    public static function attempt(string $email, string $password): bool
    {
        $u = (new UtenteRepo())->trovaAttivoPerEmail(trim($email));
        // Verifica sempre, anche con hash fittizio, per non rivelare via timing quali email esistono.
        $ok = password_verify($password, $u['password_hash'] ?? self::dummyHash());
        if ($u === null || !$ok) {
            return false;
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        unset($_SESSION['csrf']); // nuovo token dopo il login
        $_SESSION['utente_id'] = (int) $u['id'];
        return true;
    }

    private static function dummyHash(): string
    {
        static $hash = null;
        return $hash ??= password_hash('x', PASSWORD_DEFAULT);
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
        $u = self::user();
        if ($u === null) {
            throw new HttpException(401, 'Accesso richiesto');
        }
        if ($u['ruolo'] !== $ruolo) {
            throw new HttpException(403, 'Accesso negato');
        }
    }
}
