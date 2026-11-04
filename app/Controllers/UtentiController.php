<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Csrf;
use App\View;
use App\Repos\UtenteRepo;

/** Gestione utenti (solo admin). La password non viene mai riproposta ne' registrata. */
final class UtentiController
{
    private const PASSWORD_MIN = 12;

    private UtenteRepo $repo;

    public function __construct(?UtenteRepo $repo = null)
    {
        $this->repo = $repo ?? new UtenteRepo();
    }

    public function lista(): void
    {
        Auth::requireRole('admin');
        View::render('utenti/lista', ['utenti' => $this->repo->elencoTutti()], 'Utenti');
    }

    public function nuovo(): void
    {
        Auth::requireRole('admin');
        $this->mostraForm(['nome' => '', 'email' => '', 'ruolo' => 'collaboratore', 'attivo' => 1], []);
    }

    public function crea(): void
    {
        Auth::requireRole('admin');
        $this->salva(null);
    }

    public function modifica(string $id): void
    {
        Auth::requireRole('admin');
        $utente = $this->repo->trova((int) $id);
        if ($utente === null) {
            $this->nonTrovato();
            return;
        }
        $this->mostraForm($utente, []);
    }

    public function aggiorna(string $id): void
    {
        Auth::requireRole('admin');
        $this->salva((int) $id);
    }

    private function salva(?int $id): void
    {
        $dati = $this->daPost($id);
        if (!Csrf::check((string) ($_POST['csrf'] ?? ''))) {
            http_response_code(400);
            $this->mostraForm($dati, ['Sessione scaduta, riprova.']);
            return;
        }
        if ($id !== null && $this->repo->trova($id) === null) {
            $this->nonTrovato();
            return;
        }
        // Il testo in chiaro resta solo in funzioni che non toccano il DB (niente password nei backtrace).
        $password = (string) ($_POST['password'] ?? '');
        $errori = array_merge($this->erroriPassword($password, $id === null), $this->valida($dati, $id));
        if ($errori === [] && $id !== null) {
            $errori = $this->guardie($id, $dati);
        }
        if ($errori !== []) {
            http_response_code(422);
            $this->mostraForm($dati, $errori);
            return;
        }
        $hash = $this->calcolaHash($password);
        try {
            if ($id === null) {
                $this->repo->crea($dati, (string) $hash);
            } else {
                $this->repo->aggiorna($id, $dati, $hash);
            }
        } catch (\DomainException $e) {
            http_response_code(422);
            $this->mostraForm($dati, [$e->getMessage()]);
            return;
        } catch (\PDOException $e) {
            if ($this->repo->emailInUso($dati['email'], $id)) { // race sul vincolo UNIQUE
                http_response_code(422);
                $this->mostraForm($dati, ['Email gia\' in uso da un altro utente.']);
                return;
            }
            throw $e;
        }
        header('Location: /utenti');
    }

    /** @return string[] */
    private function valida(array $d, ?int $id): array
    {
        $e = [];
        if ($d['nome'] === '') {
            $e[] = 'Il nome e\' obbligatorio.';
        } elseif (mb_strlen($d['nome']) > 120) {
            // La colonna utenti.nome e' VARCHAR(120) (migrazione 001): oltre darebbe errore SQL.
            $e[] = 'Il nome non puo\' superare 120 caratteri.';
        }
        if ($d['email'] === '') {
            $e[] = 'L\'email e\' obbligatoria.';
        } elseif (mb_strlen($d['email']) > 191 || filter_var($d['email'], FILTER_VALIDATE_EMAIL) === false) {
            $e[] = 'Email non valida (massimo 191 caratteri).';
        } elseif ($this->repo->emailInUso($d['email'], $id)) {
            $e[] = 'Email gia\' in uso da un altro utente.';
        }
        if (!in_array($d['ruolo'], Auth::RUOLI, true)) {
            $e[] = 'Ruolo non valido.';
        }
        return $e;
    }

    /** @return string[] */
    private function erroriPassword(#[\SensitiveParameter] string $password, bool $obbligatoria): array
    {
        if ($obbligatoria && $password === '') {
            return ['La password e\' obbligatoria.'];
        }
        if ($password !== '' && mb_strlen($password) < self::PASSWORD_MIN) {
            return ['La password deve avere almeno ' . self::PASSWORD_MIN . ' caratteri.'];
        }
        return [];
    }

    private function calcolaHash(#[\SensitiveParameter] string $password): ?string
    {
        return $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : null;
    }

    /** @return string[] */
    private function guardie(int $id, array $d): array
    {
        $me = Auth::user();
        if ($me !== null && $me['id'] === $id && ($d['attivo'] !== 1 || $d['ruolo'] !== 'admin')) {
            return ['Non puoi disattivare o declassare te stesso.'];
        }
        return [];
    }

    private function daPost(?int $id): array
    {
        $dati = [
            'nome' => trim((string) ($_POST['nome'] ?? '')),
            'email' => trim((string) ($_POST['email'] ?? '')),
            'ruolo' => (string) ($_POST['ruolo'] ?? ''),
            'attivo' => !empty($_POST['attivo']) ? 1 : 0,
        ];
        if ($id !== null) {
            $dati['id'] = $id;
        }
        return $dati;
    }

    private function mostraForm(array $utente, array $errori): void
    {
        View::render('utenti/form', ['utente' => $utente, 'errori' => $errori]);
    }

    private function nonTrovato(): void
    {
        http_response_code(404);
        echo 'Utente non trovato';
    }
}
