<?php
/** @var array $clienti */

$h = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
$titolo = 'Clienti';
?>
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
