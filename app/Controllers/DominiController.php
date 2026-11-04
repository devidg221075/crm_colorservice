<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Csrf;
use App\HttpException;
use App\View;
use App\Repos\ClienteRepo;
use App\Repos\DominioRepo;
use App\Repos\TitolareRepo;
use App\Services\Stato;
use DateTimeImmutable;
use PDOException;

final class DominiController
{
    public function __construct(
        private ?DominioRepo $repo = null,
        private ?TitolareRepo $titolari = null,
        private ?ClienteRepo $clienti = null,
    ) {
        $this->repo ??= new DominioRepo();
        $this->titolari ??= new TitolareRepo();
        $this->clienti ??= new ClienteRepo();
    }

    public function lista(): void
    {
        Auth::requireLogin();
        $admin = $this->eAdmin();
        $filtri = [
            'q' => (string) ($_GET['q'] ?? ''),
            'stato' => (string) ($_GET['stato'] ?? ''),
            'provider' => (string) ($_GET['provider'] ?? ''),
            'cliente_id' => (string) ($_GET['cliente_id'] ?? ''),
        ];
        $domini = $this->repo->lista($filtri, $admin);
        $clienti = $this->clienti->all();
        View::render('domini/lista', compact('domini', 'clienti', 'filtri', 'admin'));
    }

    public function scheda(string $id): void
    {
        Auth::requireLogin();
        $admin = $this->eAdmin();
        $dominio = $this->repo->find((int) $id, $admin);
        if ($dominio === null) {
            $this->nonTrovato();
            return;
        }
        $titolare = $this->titolari->perDominio((int) $id);
        $suggerito = $this->suggerimento($dominio);
        View::render('domini/scheda', compact('dominio', 'titolare', 'suggerito', 'admin'));
    }

    /** Solo admin: costi e margine di un dominio. */
    public function margine(string $id): void
    {
        Auth::requireRole('admin');
        $dominio = $this->repo->find((int) $id, true);
        if ($dominio === null) {
            $this->nonTrovato();
            return;
        }
        View::render('domini/margine', compact('dominio'));
    }

    public function nuovo(): void
    {
        Auth::requireLogin();
        $this->mostraForm(['nome' => '', 'provider' => 'altro', 'stato' => 'attivo'], [], [], null);
    }

    public function crea(): void
    {
        Auth::requireLogin();
        $this->salva(null);
    }

    public function modifica(string $id): void
    {
        Auth::requireLogin();
        $dominio = $this->repo->find((int) $id, $this->eAdmin());
        if ($dominio === null) {
            $this->nonTrovato();
            return;
        }
        $this->mostraForm($dominio, $this->titolari->perDominio((int) $id) ?? [], [], $this->suggerimento($dominio));
    }

    public function aggiorna(string $id): void
    {
        Auth::requireLogin();
        $this->salva((int) $id);
    }

    private function salva(?int $id): void
    {
        $admin = $this->eAdmin();
        if ($id !== null && $this->repo->find($id) === null) {
            $this->nonTrovato();
            return;
        }
        $dati = $this->daPost($id, $admin);
        $titolare = $this->titolareDaPost();
        if (!Csrf::check((string) ($_POST['csrf'] ?? ''))) {
            http_response_code(400);
            $this->mostraForm($dati, $titolare, ['Sessione scaduta, riprova.'], null);
            return;
        }
        $errori = DominioRepo::valida($dati);
        if ($errori !== []) {
            http_response_code(422);
            $this->mostraForm($dati, $titolare, $errori, null);
            return;
        }
        try {
            $salvato = $this->repo->save($dati);
        } catch (PDOException $e) {
            if ($e->getCode() !== '23000') {
                throw $e;
            }
            http_response_code(422);
            $this->mostraForm($dati, $titolare, ['Esiste gia\' un dominio con questo nome.'], null);
            return;
        }
        $this->titolari->salva($salvato, $titolare);
        header('Location: /domini/' . $salvato);
    }

    /** Le chiavi di costo entrano nei dati solo per l'admin: un collaboratore non puo' scriverle. */
    private function daPost(?int $id, bool $admin): array
    {
        $dati = [
            'nome' => (string) ($_POST['nome'] ?? ''),
            'cliente_id' => (int) ($_POST['cliente_id'] ?? 0),
            'provider' => (string) ($_POST['provider'] ?? ''),
            'stato' => (string) ($_POST['stato'] ?? ''),
            'data_acquisto' => (string) ($_POST['data_acquisto'] ?? ''),
            'data_scadenza' => (string) ($_POST['data_scadenza'] ?? ''),
            'nameserver' => (string) ($_POST['nameserver'] ?? ''),
            'note_dns' => (string) ($_POST['note_dns'] ?? ''),
            'codice_auth' => (string) ($_POST['codice_auth'] ?? ''),
        ];
        if ($admin) {
            $dati['costo_acquisto'] = self::decimale($_POST['costo_acquisto'] ?? '');
            $dati['prezzo_rivendita'] = self::decimale($_POST['prezzo_rivendita'] ?? '');
        }
        if ($id !== null) {
            $dati['id'] = $id;
        }
        return $dati;
    }

    private function titolareDaPost(): array
    {
        $t = [];
        foreach (TitolareRepo::CAMPI as $c) {
            $t[$c] = (string) ($_POST['titolare_' . $c] ?? '');
        }
        return $t;
    }

    private static function decimale(mixed $v): float|string|null
    {
        $s = str_replace(',', '.', trim((string) $v));
        if ($s === '') {
            return null;
        }
        return is_numeric($s) ? (float) $s : $s;
    }

    /** Stato suggerito (solo se diverso dall'attuale); non viene mai applicato in automatico. */
    private function suggerimento(array $dominio): ?string
    {
        if (empty($dominio['data_scadenza'])) {
            return null;
        }
        try {
            $scadenza = new DateTimeImmutable((string) $dominio['data_scadenza']);
        } catch (\Exception) {
            return null;
        }
        $giorni = Stato::giorniAllaScadenza(new DateTimeImmutable('today'), $scadenza);
        $s = Stato::suggerito($giorni, (string) $dominio['stato']);
        return $s !== $dominio['stato'] ? $s : null;
    }

    private function eAdmin(): bool
    {
        return (Auth::user()['ruolo'] ?? null) === 'admin';
    }

    private function mostraForm(array $dominio, array $titolare, array $errori, ?string $suggerito): void
    {
        $admin = $this->eAdmin();
        $clienti = $this->clienti->all();
        View::render('domini/form', compact('dominio', 'titolare', 'errori', 'clienti', 'suggerito', 'admin'));
    }

    private function nonTrovato(): void
    {
        throw new HttpException(404, 'Dominio non trovato');
    }
}
