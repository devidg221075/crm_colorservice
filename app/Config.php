<?php
declare(strict_types=1);

namespace App;

final class Config
{
    private static ?array $data = null;

    /** Carica app.php e, se presente, local.php dalla cartella indicata (default: config/). */
    public static function load(?string $dir = null): void
    {
        $dir = rtrim($dir ?? dirname(__DIR__) . '/config', '/');
        $base = is_file($dir . '/app.php') ? require $dir . '/app.php' : [];
        $local = is_file($dir . '/local.php') ? require $dir . '/local.php' : [];
        self::$data = array_replace_recursive($base, $local);
        // Fuso unico per web e cron: "oggi" deve coincidere ovunque.
        date_default_timezone_set((string) (self::$data['app']['timezone'] ?? 'Europe/Rome'));
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (self::$data === null) {
            self::load();
        }
        $value = self::$data;
        foreach (explode('.', $key) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }
        return $value;
    }
}
