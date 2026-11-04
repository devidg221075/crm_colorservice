<?php
declare(strict_types=1);

// Lanciato ogni giorno dal cron dell'hosting: php cron/alert.php
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Services\AlertService;
use App\Services\Mailer;

date_default_timezone_set('Europe/Rome');

try {
    $n = (new AlertService())->run(new DateTimeImmutable('today'), new Mailer());
    echo "Alert inviati: $n\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Errore: ' . $e->getMessage() . "\n");
    exit(1);
}
