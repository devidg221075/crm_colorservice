<?php
/** @var array $dominio */
/** @var array $titolare */
/** @var string[] $errori */
/** @var array $clienti */
/** @var string|null $suggerito */
/** @var bool $admin */
use App\Config;
use App\Csrf;
use App\Repos\DominioRepo;
use App\Services\Stato;

$h = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
$v = static fn(array $a, string $k): string => $h((string) ($a[$k] ?? ''));
$esistente = !empty($dominio['id']);
$azione = $esistente ? '/domini/' . (int) $dominio['id'] : '/domini';
$titolo = $esistente ? 'Modifica dominio' : 'Nuovo dominio';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $h($titolo) ?> - <?= $h((string) Config::get('app.nome')) ?></title>
</head>
<body>
    <h1><?= $h($titolo) ?></h1>
    <?php foreach ($errori as $e): ?>
        <p role="alert"><?= $h($e) ?></p>
    <?php endforeach; ?>
    <?php if ($suggerito !== null): ?>
        <p role="status">Stato suggerito: <strong><?= $h($suggerito) ?></strong>. Non viene applicato automaticamente: selezionalo qui sotto se vuoi confermarlo.</p>
    <?php endif; ?>
    <form method="post" action="<?= $h($azione) ?>">
        <?= Csrf::field() ?>
        <label>Nome dominio
            <input type="text" name="nome" maxlength="253" required value="<?= $v($dominio, 'nome') ?>">
        </label>
        <label>Cliente
            <select name="cliente_id">
                <option value="0">Nessun cliente</option>
                <?php foreach ($clienti as $c): ?>
                    <option value="<?= (int) $c['id'] ?>"<?= (int) ($dominio['cliente_id'] ?? 0) === (int) $c['id'] ? ' selected' : '' ?>><?= $h((string) $c['ragione_sociale']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Provider
            <select name="provider">
                <?php foreach (DominioRepo::PROVIDER as $p): ?>
                    <option value="<?= $h($p) ?>"<?= ($dominio['provider'] ?? '') === $p ? ' selected' : '' ?>><?= $h($p) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Stato
            <select name="stato">
                <?php foreach (Stato::VALORI as $s): ?>
                    <option value="<?= $h($s) ?>"<?= ($dominio['stato'] ?? '') === $s ? ' selected' : '' ?>><?= $h($s) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Data di acquisto
            <input type="date" name="data_acquisto" value="<?= $v($dominio, 'data_acquisto') ?>">
        </label>
        <label>Data di scadenza
            <input type="date" name="data_scadenza" value="<?= $v($dominio, 'data_scadenza') ?>">
        </label>
        <label>Nameserver
            <textarea name="nameserver" rows="3"><?= $v($dominio, 'nameserver') ?></textarea>
        </label>
        <label>Note DNS
            <textarea name="note_dns" rows="3"><?= $v($dominio, 'note_dns') ?></textarea>
        </label>
        <label>Codice EPP (auth)
            <input type="text" name="codice_auth" value="<?= $v($dominio, 'codice_auth') ?>">
        </label>

        <h2>Titolare</h2>
        <label>Ragione sociale <input type="text" name="titolare_ragione_sociale" value="<?= $v($titolare, 'ragione_sociale') ?>"></label>
        <label>Codice fiscale / P. IVA <input type="text" name="titolare_cf_piva" value="<?= $v($titolare, 'cf_piva') ?>"></label>
        <label>Indirizzo <input type="text" name="titolare_indirizzo" value="<?= $v($titolare, 'indirizzo') ?>"></label>
        <label>PEC <input type="email" name="titolare_pec" value="<?= $v($titolare, 'pec') ?>"></label>
        <label>Email <input type="email" name="titolare_email" value="<?= $v($titolare, 'email') ?>"></label>
        <label>Telefono <input type="text" name="titolare_telefono" value="<?= $v($titolare, 'telefono') ?>"></label>

        <?php if ($admin): ?>
            <h2>Costi</h2>
            <label>Costo di acquisto (EUR) <input type="text" inputmode="decimal" name="costo_acquisto" value="<?= $v($dominio, 'costo_acquisto') ?>"></label>
            <label>Prezzo di rivendita (EUR) <input type="text" inputmode="decimal" name="prezzo_rivendita" value="<?= $v($dominio, 'prezzo_rivendita') ?>"></label>
        <?php endif; ?>

        <button type="submit">Salva</button>
        <a href="/domini">Annulla</a>
    </form>
</body>
</html>
