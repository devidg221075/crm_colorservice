<?php
/** @var array $utenti */

$h = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
$titolo = 'Utenti';
?>
    <h1>Utenti</h1>
    <p><a href="/utenti/nuovo">Nuovo utente</a></p>
    <table>
        <thead>
            <tr><th>Nome</th><th>Email</th><th>Ruolo</th><th>Attivo</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($utenti as $u): ?>
            <tr>
                <td><?= $h((string) $u['nome']) ?></td>
                <td><?= $h((string) $u['email']) ?></td>
                <td><?= $h((string) $u['ruolo']) ?></td>
                <td><?= (int) $u['attivo'] === 1 ? 'Si' : 'No' ?></td>
                <td><a href="/utenti/<?= (int) $u['id'] ?>/modifica">Modifica</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
