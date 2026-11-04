<?php
/** @var int $nonLette */
?>
<a class="btn btn-outline-light btn-sm position-relative" href="/notifiche">
    Notifiche
    <?php if ($nonLette > 0): ?>
        <span class="badge text-bg-danger ms-1" data-notifiche-badge><?= (int) $nonLette ?></span>
    <?php endif; ?>
</a>
