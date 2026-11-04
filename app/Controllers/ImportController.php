<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Csrf;
use App\Services\CsvImporter;
use App\Services\ImportPreview;
use Throwable;

/** Import dei domini da CSV: azione riservata all'admin. Solo .csv in questa versione (niente .xlsx). */
final class ImportController
{
    private const MAX_BYTE = 2 * 1024 * 1024;

    public function mostra(): void
    {
        Auth::requireRole('admin');
        $this->render(null, null, []);
    }

    public function anteprima(): void
    {
        Auth::requireRole('admin');
        if (!Csrf::check((string) ($_POST['csrf'] ?? ''))) {
            http_response_code(400);
            $this->render(null, null, ['Sessione scaduta, riprova.']);
            return;
        }
        $provider = (string) ($_POST['provider'] ?? '');
        $f = $_FILES['file'] ?? null;
        $errori = [];
        if (!in_array($provider, ['aruba', 'serverplan'], true)) {
            $errori[] = 'Scegli il provider.';
        }
        if (!is_array($f) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $errori[] = 'Carica un file CSV.';
        } elseif (strtolower(pathinfo((string) $f['name'], PATHINFO_EXTENSION)) !== 'csv') {
            $errori[] = 'Sono accettati solo file .csv.';
        } elseif ((int) $f['size'] > self::MAX_BYTE) {
            $errori[] = 'Il file supera i 2 MB.';
        }
        if ($errori === []) {
            try {
                $p = CsvImporter::parse((string) $f['tmp_name'], $provider, basename((string) $f['name']));
                $_SESSION['import_preview'] = $p->toArray();
                $this->render($p, null, []);
                return;
            } catch (Throwable) {
                $errori[] = 'Impossibile leggere il file.';
            }
        }
        http_response_code(422);
        $this->render(null, null, $errori);
    }

    public function conferma(): void
    {
        Auth::requireRole('admin');
        if (!Csrf::check((string) ($_POST['csrf'] ?? ''))) {
            http_response_code(400);
            $this->render(null, null, ['Sessione scaduta, riprova.']);
            return;
        }
        $raw = $_SESSION['import_preview'] ?? null;
        if (!is_array($raw)) {
            http_response_code(422);
            $this->render(null, null, ['Nessuna anteprima da confermare: carica di nuovo il file.']);
            return;
        }
        unset($_SESSION['import_preview']); // una conferma vale una volta sola
        $esito = CsvImporter::applica(ImportPreview::fromArray($raw), (int) (Auth::user()['id'] ?? 0));
        $this->render(null, $esito, []);
    }

    private function render(?ImportPreview $anteprima, ?array $esito, array $errori): void
    {
        require dirname(__DIR__, 2) . '/views/import/anteprima.php';
    }
}
