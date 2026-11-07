<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\TestDatabaseGuard;

class TestDatabaseGuardTest extends TestCase
{
    private const SAFE = [
        'driver' => 'mysql', 'database' => 'roost_testing', 'username' => 'roost_test',
        'host' => '127.0.0.1', 'port' => 3306, 'url' => '',
    ];

    public function test_accepts_the_isolated_database(): void
    {
        TestDatabaseGuard::assertSafe('testing', 'mysql', self::SAFE);
        $this->addToAssertionCount(1);
    }

    #[DataProvider('unsafeConfigurations')]
    public function test_rejects_unsafe_configuration(string $environment, array $overrides): void
    {
        $this->expectException(RuntimeException::class);
        TestDatabaseGuard::assertSafe($environment, 'mysql', array_replace(self::SAFE, $overrides));
    }

    public static function unsafeConfigurations(): array
    {
        return [
            'application database' => ['testing', ['database' => 'roost']],
            'admin user' => ['testing', ['username' => 'root']],
            'non-test environment' => ['local', []],
            'URL override' => ['testing', ['url' => 'mysql://localhost/roost']],
            'remote host' => ['testing', ['host' => 'remote.example']],
            'write override' => ['testing', ['write' => ['database' => 'roost']]],
        ];
    }
}
