<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;
use Tests\Support\TestDatabaseGuard;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();

        // Runs before Laravel sets up RefreshDatabase or any other test trait.
        $name = $app['config']->get('database.default');
        $config = $app['config']->get("database.connections.{$name}", []);
        TestDatabaseGuard::assertSafe($app->environment(), $name, $config);

        $connection = $app['db']->connection($name);
        TestDatabaseGuard::assertSafe($app->environment(), $name, $connection->getConfig());
        $identity = $connection->selectOne('SELECT DATABASE() AS database_name, CURRENT_USER() AS account');

        if ($identity->database_name !== 'roost_testing' || $identity->account !== 'roost_test@localhost') {
            throw new RuntimeException('Unexpected test database/account; refusing database resets.');
        }

        return $app;
    }
}
