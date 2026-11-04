<?php
/** @var ?string $errore */
use App\Config;
use App\Csrf;

$h = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Accesso - <?= $h((string) Config::get('app.nome')) ?></title>
</head>
<body>
    <h1><?= $h((string) Config::get('app.nome')) ?></h1>
    <?php if (!empty($errore)): ?>
        <p role="alert"><?= $h($errore) ?></p>
    <?php endif; ?>
    <form method="post" action="/login">
        <?= Csrf::field() ?>
        <label>Email <input type="email" name="email" required autofocus></label>
        <label>Password <input type="password" name="password" required></label>
        <button type="submit">Accedi</button>
    </form>
</body>
</html>
