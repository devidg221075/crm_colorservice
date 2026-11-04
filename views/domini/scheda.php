<?php
/** @var array $dominio */
/** @var array|null $titolare */
/** @var string|null $suggerito */
/** @var bool $admin */
use App\Config;

$h = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
$euro = static fn(mixed $v): string => $v === null ? '-' : number_format((float) $v, 2, ',', '.') . ' EUR';
$val = static fn(mixed $v): string => ($v === null || $v === '') ? '-' : (string) $v;
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $h((string) $dominio['nome']) ?> - <?= $h((string) Config::get('app.nome')) ?></title>
</head>
<body>
    <h1><?= $h((string) $dominio['nome']) ?></h1>
    <p><a href="/domini">Torna all'elenco</a> | <a href="/domini/<?= (int) $dominio['id'] ?>/modifica">Modifica</a></p>
    <?php if ($suggerito !== null): ?>
        <p role="status">Stato suggerito: <strong><?= $h($suggerito) ?></strong> (l'attuale e' <?= $h((string) $dominio['stato']) ?>). Il suggerimento non viene applicato automaticamente: modifica il dominio per confermarlo.</p>
    <?php endif; ?>
    <dl>
        <dt>Cliente</dt><dd><?= $h($val($dominio['cliente_nome'] ?? null)) ?></dd>
        <dt>Provider</dt><dd><?= $h((string) $dominio['provider']) ?></dd>
        <dt>Stato</dt><dd><?= $h((string) $dominio['stato']) ?></dd>
        <dt>Data di acquisto</dt><dd><?= $h($val($dominio['data_acquisto'])) ?></dd>
        <dt>Data di scadenza</dt><dd><?= $h($val($dominio['data_scadenza'])) ?></dd>
        <dt>Nameserver</dt><dd><?= nl2br($h($val($dominio['nameserver']))) ?></dd>
        <dt>Note DNS</dt><dd><?= nl2br($h($val($dominio['note_dns']))) ?></dd>
        <dt>Codice EPP (auth)</dt><dd><?= $h($val($dominio['codice_auth'])) ?></dd>
    </dl>
    <h2>Titolare</h2>
    <?php if ($titolare === null): ?>
        <p>Nessun dato del titolare.</p>
    <?php else: ?>
        <dl>
            <dt>Ragione sociale</dt><dd><?= $h($val($titolare['ragione_sociale'])) ?></dd>
            <dt>Codice fiscale / P. IVA</dt><dd><?= $h($val($titolare['cf_piva'])) ?></dd>
            <dt>Indirizzo</dt><dd><?= $h($val($titolare['indirizzo'])) ?></dd>
            <dt>PEC</dt><dd><?= $h($val($titolare['pec'])) ?></dd>
            <dt>Email</dt><dd><?= $h($val($titolare['email'])) ?></dd>
            <dt>Telefono</dt><dd><?= $h($val($titolare['telefono'])) ?></dd>
        </dl>
    <?php endif; ?>
    <?php if ($admin): ?>
        <h2>Costi</h2>
        <dl>
            <dt>Costo di acquisto</dt><dd><?= $h($euro($dominio['costo_acquisto'] ?? null)) ?></dd>
            <dt>Prezzo di rivendita</dt><dd><?= $h($euro($dominio['prezzo_rivendita'] ?? null)) ?></dd>
            <dt>Margine</dt><dd><?= $h($euro($dominio['margine'] ?? null)) ?></dd>
        </dl>
    <?php endif; ?>
</body>
</html>
