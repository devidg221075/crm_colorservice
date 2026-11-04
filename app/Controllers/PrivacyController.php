<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Csrf;
use App\View;
use App\Services\PrivacyService;

/** Privacy: ricerca per email, esportazione e cancellazione dei dati del titolare. Solo admin. */
final class PrivacyController
{
    public const PAROLA_CONFERMA = 'CANCELLA';

    private PrivacyService $service;

    public function __construct(?PrivacyService $service = null)
    {
        $this->service = $service ?? new PrivacyService();
    }

    /** GET: solo il modulo di ricerca, mai modifiche. */
    public function mostra(): void
    {
        Auth::requireRole('admin');
        $this->render(['email' => '', 'risultati' => null, 'esito' => null, 'errori' => []]);
    }

    public function cerca(): void
    {
        Auth::requireRole('admin');
        $email = trim((string) ($_POST['email'] ?? ''));
        if (!Csrf::check((string) ($_POST['csrf'] ?? ''))) {
            http_response_code(400);
            $this->render(['email' => $email, 'risultati' => null, 'esito' => null, 'errori' => ['Sessione scaduta, riprova.']]);
            return;
        }
        if ($email === '') {
            http_response_code(422);
            $this->render(['email' => '', 'risultati' => null, 'esito' => null, 'errori' => ['Inserisci un indirizzo email.']]);
            return;
        }
        $this->render(['email' => $email, 'risultati' => $this->service->esporta($email), 'esito' => null, 'errori' => []]);
    }

    public function cancella(): void
    {
        Auth::requireRole('admin');
        $email = trim((string) ($_POST['email'] ?? ''));
        if (!Csrf::check((string) ($_POST['csrf'] ?? ''))) {
            http_response_code(400);
            $this->render(['email' => $email, 'risultati' => null, 'esito' => null, 'errori' => ['Sessione scaduta, riprova.']]);
            return;
        }
        if ($email === '' || (string) ($_POST['conferma'] ?? '') !== self::PAROLA_CONFERMA) {
            http_response_code(422);
            $this->render([
                'email' => $email,
                'risultati' => $email === '' ? null : $this->service->esporta($email),
                'esito' => null,
                'errori' => ['Per cancellare digita ' . self::PAROLA_CONFERMA . ' nel campo di conferma.'],
            ]);
            return;
        }
        $n = $this->service->cancella($email, (int) (Auth::user()['id'] ?? 0));
        $this->render(['email' => '', 'risultati' => null, 'esito' => $n, 'errori' => []]);
    }

    private function render(array $dati): void
    {
        $dati['parolaConferma'] = self::PAROLA_CONFERMA;
        View::render('privacy/scheda', $dati);
    }
}
