<?php
declare(strict_types=1);

namespace App;

final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    public static function check(string $token): bool
    {
        $atteso = $_SESSION['csrf'] ?? null;
        return is_string($atteso) && $token !== '' && hash_equals($atteso, $token);
    }

    /** Campo nascosto da inserire in ogni form POST. */
    public static function field(): string
    {
        return '<input type="hidden" name="csrf" value="' . self::token() . '">';
    }
}
