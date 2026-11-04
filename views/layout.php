<?php
/**
 * Layout condiviso. Variabili attese: $titolo (string), $contenuto (HTML gia' pronto e sicuro).
 */
use App\Auth;
use App\Config;
use App\Csrf;
use App\Repos\NotificaRepo;

$h = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
$utenteLayout = Auth::user();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $h((string) $titolo) ?> - <?= $h((string) Config::get('app.nome')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
</head>
<body>
<?php if ($utenteLayout !== null): ?>
    <nav class="navbar navbar-dark bg-dark px-3">
        <span class="navbar-brand"><?= $h((string) Config::get('app.nome')) ?></span>
        <ul class="navbar-nav flex-row gap-3 me-auto ms-3">
            <li class="nav-item"><a class="nav-link" href="/clienti">Clienti</a></li>
            <li class="nav-item"><a class="nav-link" href="/domini">Domini</a></li>
            <?php if ($utenteLayout['ruolo'] === 'admin'): ?>
                <li class="nav-item"><a class="nav-link" href="/import">Import</a></li>
                <li class="nav-item"><a class="nav-link" href="/privacy">Privacy</a></li>
            <?php endif; ?>
        </ul>
        <div class="d-flex align-items-center gap-2">
            <?php
            $nonLette = count((new NotificaRepo())->nonLette($utenteLayout['id']));
            require __DIR__ . '/partials/campanella.php';
            ?>
            <form method="post" action="/logout" class="m-0">
                <?= Csrf::field() ?>
                <button type="submit" class="btn btn-outline-light btn-sm">Esci</button>
            </form>
        </div>
    </nav>
<?php endif; ?>
    <main class="container py-4">
        <?= $contenuto ?>
    </main>
</body>
</html>
