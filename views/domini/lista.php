<?php
/** @var array $domini */
/** @var array $clienti */
/** @var array $filtri */
/** @var bool $admin */
use App\Config;
use App\Repos\DominioRepo;
use App\Services\Stato;

$h = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
$euro = static fn(mixed $v): string => $v === null ? '-' : number_format((float) $v, 2, ',', '.') . ' EUR';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Domini - <?= $h((string) Config::get('app.nome')) ?></title>
</head>
<body>
    <h1>Domini</h1>
    <p><a href="/domini/nuovo">Nuovo dominio</a></p>
    <form method="get" action="/domini">
        <input type="search" name="q" placeholder="Cerca dominio" value="<?= $h($filtri['q']) ?>">
        <select name="stato">
            <option value="">Tutti gli stati</option>
            <?php foreach (Stato::VALORI as $s): ?>
                <option value="<?= $h($s) ?>"<?= $filtri['stato'] === $s ? ' selected' : '' ?>><?= $h($s) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="provider">
            <option value="">Tutti i provider</option>
            <?php foreach (DominioRepo::PROVIDER as $p): ?>
                <option value="<?= $h($p) ?>"<?= $filtri['provider'] === $p ? ' selected' : '' ?>><?= $h($p) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="cliente_id">
            <option value="">Tutti i clienti</option>
            <?php foreach ($clienti as $c): ?>
                <option value="<?= (int) $c['id'] ?>"<?= $filtri['cliente_id'] === (string) $c['id'] ? ' selected' : '' ?>><?= $h((string) $c['ragione_sociale']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit">Filtra</button>
    </form>
    <?php if ($domini === []): ?>
        <p>Nessun dominio trovato.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Dominio</th><th>Cliente</th><th>Provider</th><th>Scadenza</th><th>Stato</th>
                    <?php if ($admin): ?><th>Costo acquisto</th><th>Prezzo rivendita</th><th>Margine</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($domini as $d): ?>
                <tr>
                    <td><a href="/domini/<?= (int) $d['id'] ?>"><?= $h((string) $d['nome']) ?></a></td>
                    <td><?= $h((string) ($d['cliente_nome'] ?? '')) ?></td>
                    <td><?= $h((string) $d['provider']) ?></td>
                    <td><?= $h((string) ($d['data_scadenza'] ?? '')) ?></td>
                    <td><?= $h((string) $d['stato']) ?></td>
                    <?php if ($admin): ?>
                        <td><?= $h($euro($d['costo_acquisto'] ?? null)) ?></td>
                        <td><?= $h($euro($d['prezzo_rivendita'] ?? null)) ?></td>
                        <td><?= $h($euro($d['margine'] ?? null)) ?></td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</body>
</html>
