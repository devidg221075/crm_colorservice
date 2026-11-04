<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Csrf;

final class AuthController
{
    public function mostraLogin(?string $errore = null): void
    {
        require dirname(__DIR__, 2) . '/views/login.php';
    }

    public function login(): void
    {
        if (!Csrf::check((string) ($_POST['csrf'] ?? ''))) {
            http_response_code(400);
            $this->mostraLogin('Sessione scaduta, riprova.');
            return;
        }
        if (Auth::attempt((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''))) {
            header('Location: /');
            return;
        }
        http_response_code(401);
        $this->mostraLogin('Email o password non corretti.');
    }

    public function logout(): void
    {
        if (Csrf::check((string) ($_POST['csrf'] ?? ''))) {
            Auth::logout();
        }
        header('Location: /login');
    }
}
