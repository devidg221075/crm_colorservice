<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Config;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    public function test_local_overrides_app(): void
    {
        Config::load(__DIR__ . '/fixtures/cfg');
        $this->assertSame('mysql', Config::get('db.driver'));
        $this->assertSame('override', Config::get('app.nome'));
    }

    public function test_missing_key_returns_default(): void
    {
        Config::load(__DIR__ . '/fixtures/cfg');
        $this->assertSame('x', Config::get('non.esiste', 'x'));
    }
}
