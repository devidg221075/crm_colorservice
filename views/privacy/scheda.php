<?php
/** @var string $email @var ?array $risultati @var ?int $esito @var string[] $errori @var string $parolaConferma */
use App\Csrf;

$h = static fn(?string $s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$titolo = 'Privacy titolare';
$campi = ['ragione_sociale' => 'Ragione sociale', 'cf_piva' => 'CF / P.IVA', 'indirizzo' => 'Indirizzo',
    'pec' => 'PEC', 'email' => 'Email', 'telefono' => 'Telefono'];
?>
    <h1>Privacy: dati del titolare</h1>
    <?php foreach ($errori as $e): ?>
        <p role="alert"><?= $h($e) ?></p>
    <?php endforeach; ?>

    <?php if ($esito !== null): ?>
        <?php if ((int) $esito === 0): ?>
            <p>Nessun titolare trovato.</p>
        <?php else: ?>
            <p><?= (int) $esito ?> titolari anonimizzati. I domini collegati sono stati conservati.</p>
        <?php endif; ?>
    <?php endif; ?>

    <form method="post" action="/privacy/cerca">
        <?= Csrf::field() ?>
        <label for="email">Email del titolare</label>
        <input type="email" id="email" name="email" value="<?= $h($email) ?>" required>
        <button type="submit">Cerca</button>
    </form>

    <?php if ($risultati !== null): ?>
        <h2>Dati trovati per <?= $h($email) ?></h2>
        <?php if ($risultati === []): ?>
            <p>Nessun titolare con questa email.</p>
        <?php else: ?>
            <?php foreach ($risultati as $t): ?>
                <table>
                    <?php foreach ($campi as $k => $etichetta): ?>
                        <tr><th><?= $h($etichetta) ?></th><td><?= $h($t[$k] ?? '') ?></td></tr>
                    <?php endforeach; ?>
                    <tr><th>Dominio</th><td><?= $h($t['dominio']['nome'] ?? '') ?></td></tr>
                    <tr><th>Scadenza</th><td><?= $h($t['dominio']['data_scadenza'] ?? '') ?></td></tr>
                </table>
            <?php endforeach; ?>

            <h2>Cancellazione</h2>
            <p>L'operazione anonimizza in modo irreversibile i dati personali di questi <?= count($risultati) ?> titolari. I domini restano.</p>
            <form method="post" action="/privacy/cancella">
                <?= Csrf::field() ?>
                <input type="hidden" name="email" value="<?= $h($email) ?>">
                <label for="conferma">Digita <?= $h($parolaConferma) ?> per confermare</label>
                <input type="text" id="conferma" name="conferma" autocomplete="off" required>
                <button type="submit">Cancella i dati</button>
            </form>
        <?php endif; ?>
    <?php endif; ?>
