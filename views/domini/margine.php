<?php
/** @var array $dominio */
use App\Config;

$h = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
$euro = static fn(mixed $v): string => $v === null ? '-' : number_format((float) $v, 2, ',', '.') . ' EUR';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Margine <?= $h((string) $dominio['nome']) ?> - <?= $h((string) Config::get('app.nome')) ?></title>
</head>
<body>
    <h1>Margine: <?= $h((string) $dominio['nome']) ?></h1>
    <dl>
        <dt>Costo di acquisto</dt><dd><?= $h($euro($dominio['costo_acquisto'])) ?></dd>
        <dt>Prezzo di rivendita</dt><dd><?= $h($euro($dominio['prezzo_rivendita'])) ?></dd>
        <dt>Margine</dt><dd><?= $h($euro($dominio['margine'])) ?></dd>
    </dl>
    <p><a href="/domini/<?= (int) $dominio['id'] ?>">Torna alla scheda</a></p>
</body>
</html>
