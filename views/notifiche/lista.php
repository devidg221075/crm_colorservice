<?php
/** @var array $notifiche */
use App\Csrf;

$h = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
?>
<h1 class="h3">Notifiche</h1>
<?php if ($notifiche === []): ?>
    <p>Nessuna nuova notifica.</p>
<?php else: ?>
    <ul class="list-group mb-3">
    <?php foreach ($notifiche as $n): ?>
        <li class="list-group-item">
            <?php if ($n['dominio_id'] !== null): ?>
                <a href="/domini/<?= (int) $n['dominio_id'] ?>"><?= $h((string) $n['testo']) ?></a>
            <?php else: ?>
                <?= $h((string) $n['testo']) ?>
            <?php endif; ?>
            <small class="text-muted d-block"><?= $h((string) $n['created_at']) ?></small>
        </li>
    <?php endforeach; ?>
    </ul>
    <form method="post" action="/notifiche/lette">
        <?= Csrf::field() ?>
        <button type="submit" class="btn btn-primary">Segna tutte come lette</button>
    </form>
<?php endif; ?>
