<?php
/** @var array $dominio */

$h = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
$euro = static fn(mixed $v): string => $v === null ? '-' : number_format((float) $v, 2, ',', '.') . ' EUR';
$titolo = 'Margine ' . (string) $dominio['nome'];
?>
    <h1>Margine: <?= $h((string) $dominio['nome']) ?></h1>
    <dl>
        <dt>Costo di acquisto</dt><dd><?= $h($euro($dominio['costo_acquisto'])) ?></dd>
        <dt>Prezzo di rivendita</dt><dd><?= $h($euro($dominio['prezzo_rivendita'])) ?></dd>
        <dt>Margine</dt><dd><?= $h($euro($dominio['margine'])) ?></dd>
    </dl>
    <p><a href="/domini/<?= (int) $dominio['id'] ?>">Torna alla scheda</a></p>
