<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Auth;
use App\Config;
use PHPUnit\Framework\TestCase;

final class DeployTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function test_htaccess_rewrites_non_file_requests_to_front_controller(): void
    {
        $f = $this->root . '/public/.htaccess';
        $this->assertFileExists($f);
        $c = (string) file_get_contents($f);
        $this->assertStringContainsString('RewriteEngine On', $c);
        $this->assertStringContainsString('RewriteCond %{REQUEST_FILENAME} !-f', $c);
        $this->assertStringContainsString('RewriteRule ^ index.php [L]', $c);
    }

    public function test_config_load_sets_rome_timezone(): void
    {
        $prev = date_default_timezone_get();
        date_default_timezone_set('UTC');
        try {
            Config::load(__DIR__ . '/fixtures/cfg');
            $this->assertSame('Europe/Rome', date_default_timezone_get());
        } finally {
            date_default_timezone_set($prev);
        }
    }

    public function test_cookie_secure_follows_https_or_config(): void
    {
        $prev = $_SERVER['HTTPS'] ?? null;
        unset($_SERVER['HTTPS']);
        try {
            Config::load(__DIR__ . '/fixtures/cfg');
            $this->assertFalse(Auth::cookieSecure());
            $_SERVER['HTTPS'] = 'on';
            $this->assertTrue(Auth::cookieSecure());
            unset($_SERVER['HTTPS']);
            Config::load(__DIR__ . '/fixtures/cfg_secure');
            $this->assertTrue(Auth::cookieSecure());
        } finally {
            if ($prev !== null) {
                $_SERVER['HTTPS'] = $prev;
            }
            Config::load(__DIR__ . '/fixtures/cfg');
        }
    }

    public function test_layout_bootstrap_has_integrity(): void
    {
        $c = (string) file_get_contents($this->root . '/views/layout.php');
        $this->assertStringContainsString('integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"', $c);
        $this->assertStringContainsString('crossorigin="anonymous"', $c);
    }

    public function test_deploy_guide_dice_php_8_2(): void
    {
        $c = (string) file_get_contents($this->root . '/docs/deploy.md');
        $this->assertStringContainsString('PHP 8.2 o superiore', $c);
        $this->assertStringNotContainsString('8.1', $c);
    }

    public function test_front_controller_nasconde_gli_errori(): void
    {
        $c = (string) file_get_contents($this->root . '/public/index.php');
        $this->assertStringContainsString("ini_set('display_errors', '0')", $c);
    }
}
