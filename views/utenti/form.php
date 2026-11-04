<?php
/** @var array $utente */
/** @var string[] $errori */
use App\Auth;
use App\Csrf;

$h = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
$esistente = !empty($utente['id']);
$azione = $esistente ? '/utenti/' . (int) $utente['id'] : '/utenti';
$titolo = $esistente ? 'Modifica utente' : 'Nuovo utente';
?>
    <h1><?= $h($titolo) ?></h1>
    <?php foreach ($errori as $e): ?>
        <p role="alert"><?= $h($e) ?></p>
    <?php endforeach; ?>
    <form method="post" action="<?= $h($azione) ?>" autocomplete="off">
        <?= Csrf::field() ?>
        <label>Nome
            <input type="text" name="nome" maxlength="120" required value="<?= $h((string) $utente['nome']) ?>">
        </label>
        <label>Email
            <input type="email" name="email" maxlength="191" required value="<?= $h((string) $utente['email']) ?>">
        </label>
        <label>Password<?= $esistente ? ' (lascia vuoto per non cambiarla)' : '' ?>
            <input type="password" name="password" minlength="12" autocomplete="new-password" <?= $esistente ? '' : 'required' ?>>
        </label>
        <label>Ruolo
            <select name="ruolo">
                <?php foreach (Auth::RUOLI as $r): ?>
                    <option value="<?= $h($r) ?>"<?= ($utente['ruolo'] ?? '') === $r ? ' selected' : '' ?>><?= $h($r) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <input type="checkbox" name="attivo" value="1"<?= !empty($utente['attivo']) ? ' checked' : '' ?>> Attivo
        </label>
        <button type="submit">Salva</button>
        <a href="/utenti">Annulla</a>
    </form>
