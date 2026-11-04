<?php
/** @var array $cliente */
/** @var string[] $errori */
use App\Csrf;

$h = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
$esistente = !empty($cliente['id']);
$azione = $esistente ? '/clienti/' . (int) $cliente['id'] : '/clienti';
$titolo = $esistente ? 'Modifica cliente' : 'Nuovo cliente';
?>
    <h1><?= $h($titolo) ?></h1>
    <?php foreach ($errori as $e): ?>
        <p role="alert"><?= $h($e) ?></p>
    <?php endforeach; ?>
    <form method="post" action="<?= $h($azione) ?>">
        <?= Csrf::field() ?>
        <label>Ragione sociale
            <input type="text" name="ragione_sociale" maxlength="191" required value="<?= $h((string) $cliente['ragione_sociale']) ?>">
        </label>
        <label>Note
            <textarea name="note" rows="5"><?= $h((string) ($cliente['note'] ?? '')) ?></textarea>
        </label>
        <button type="submit">Salva</button>
        <a href="/clienti">Annulla</a>
    </form>
