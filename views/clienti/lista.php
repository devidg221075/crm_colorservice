<?php
/** @var array $clienti */
use App\Config;

$h = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Clienti - <?= $h((string) Config::get('app.nome')) ?></title>
</head>
<body>
    <h1>Clienti</h1>
    <p><a href="/clienti/nuovo">Nuovo cliente</a></p>
    <?php if ($clienti === []): ?>
        <p>Nessun cliente presente.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr><th>Ragione sociale</th><th>Note</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($clienti as $c): ?>
                <tr>
                    <td><?= $h((string) $c['ragione_sociale']) ?></td>
                    <td><?= $h((string) ($c['note'] ?? '')) ?></td>
                    <td><a href="/clienti/<?= (int) $c['id'] ?>/modifica">Modifica</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</body>
</html>
