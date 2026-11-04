<?php
declare(strict_types=1);

namespace App;

/** Errore HTTP lanciato dai controlli di accesso; il Router lo converte in risposta. */
final class HttpException extends \RuntimeException
{
    public function __construct(public readonly int $status, string $message = '')
    {
        parent::__construct($message);
    }
}
