<?php
/** @var ?\App\Services\ImportPreview $anteprima @var ?array $esito @var string[] $errori */
use App\Csrf;

$h = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
$titolo = 'Import domini';
?>
    <h1>Import domini da CSV</h1>
    <?php foreach ($errori as $e): ?>
        <p role="alert"><?= $h($e) ?></p>
    <?php endforeach; ?>

    <?php if ($esito !== null): ?>
        <p>Import completato: <?= (int) $esito['ok'] ?> righe importate, <?= (int) $esito['errore'] ?> con errori.</p>
        <p><a href="/domini">Vai ai domini</a> | <a href="/import">Nuovo import</a></p>
    <?php elseif ($anteprima !== null): ?>
        <p>File: <?= $h($anteprima->file) ?> (provider: <?= $h($anteprima->provider) ?>)</p>
        <p>Nuovi: <?= count($anteprima->nuovi) ?> | Da aggiornare: <?= count($anteprima->aggiornati) ?> | Errori: <?= count($anteprima->errori) ?></p>
        <?php foreach ([['Nuovi domini', $anteprima->nuovi], ['Domini da aggiornare', $anteprima->aggiornati]] as [$titolo, $righe]): ?>
            <h2><?= $h($titolo) ?></h2>
            <?php if ($righe === []): ?>
                <p>Nessuno.</p>
            <?php else: ?>
                <table>
                    <thead><tr><th>Riga</th><th>Dominio</th><th>Acquisto</th><th>Scadenza</th></tr></thead>
                    <tbody>
                    <?php foreach ($righe as $r): ?>
                        <tr>
                            <td><?= (int) $r['riga'] ?></td>
                            <td><?= $h((string) $r['nome']) ?></td>
                            <td><?= $h((string) ($r['data_acquisto'] ?? '')) ?></td>
                            <td><?= $h((string) $r['data_scadenza']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        <?php endforeach; ?>
        <?php if ($anteprima->errori !== []): ?>
            <h2>Righe scartate</h2>
            <ul>
            <?php foreach ($anteprima->errori as $riga => $msg): ?>
                <li>Riga <?= (int) $riga ?>: <?= $h((string) $msg) ?></li>
            <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php if ($anteprima->nuovi !== [] || $anteprima->aggiornati !== []): ?>
            <form method="post" action="/import/conferma">
                <?= Csrf::field() ?>
                <button type="submit">Conferma import</button>
            </form>
        <?php endif; ?>
        <p><a href="/import">Annulla</a></p>
    <?php else: ?>
        <form method="post" action="/import" enctype="multipart/form-data">
            <?= Csrf::field() ?>
            <p>
                <label>Provider
                    <select name="provider">
                        <option value="aruba">Aruba</option>
                        <option value="serverplan">Serverplan</option>
                    </select>
                </label>
            </p>
            <p><label>File CSV <input type="file" name="file" accept=".csv,text/csv"></label></p>
            <button type="submit">Carica e mostra anteprima</button>
        </form>
    <?php endif; ?>
