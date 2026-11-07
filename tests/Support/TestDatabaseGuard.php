<?php

namespace Tests\Support;

use RuntimeException;

final class TestDatabaseGuard
{
    public static function assertSafe(string $environment, string $connection, array $config): void
    {
        if ($environment !== 'testing'
            || $connection !== 'mysql'
            || ($config['driver'] ?? null) !== 'mysql'
            || ($config['database'] ?? null) !== 'roost_testing'
            || ($config['username'] ?? null) !== 'roost_test'
            || ($config['host'] ?? null) !== '127.0.0.1'
            || (string) ($config['port'] ?? '') !== '3306'
            || ! empty($config['url'])
            || ! empty($config['unix_socket'])
            || ! empty($config['read'])
            || ! empty($config['write'])) {
            throw new RuntimeException('Tests require local MySQL roost_testing with roost_test; refusing database resets. Clear cached config and check .env.testing.');
        }
    }
}
