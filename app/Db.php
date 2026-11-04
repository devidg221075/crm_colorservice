<?php
declare(strict_types=1);

namespace App;

use PDO;

final class Db
{
    private static ?PDO $pdo = null;

    /** Sostituisce la connessione (usato dai test); null la azzera. */
    public static function setPdo(?PDO $pdo): void
    {
        self::$pdo = $pdo;
    }

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            self::$pdo = new PDO(
                (string) Config::get('db.dsn'),
                Config::get('db.user'),
                Config::get('db.pass'),
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        }
        return self::$pdo;
    }
}
