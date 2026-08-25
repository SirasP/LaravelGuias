<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * RefreshDatabase must transact both connections because some existing
     * migrations explicitly target the fuelcontrol connection.
     *
     * @var array<int, string|null>
     */
    protected $connectionsToTransact = [null, 'fuelcontrol'];

    public function createApplication(): Application
    {
        $app = parent::createApplication();

        if (! $app->environment('testing')) {
            throw new \RuntimeException('The automated test suite must run with APP_ENV=testing.');
        }

        // Never allow tests to inherit the MySQL fuelcontrol credentials from
        // .env. An isolated in-memory connection is installed before any
        // RefreshDatabase migration can run.
        $app['config']->set('database.connections.fuelcontrol', [
            'driver' => 'sqlite',
            'url' => null,
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
            'busy_timeout' => null,
            'journal_mode' => null,
            'synchronous' => null,
            'transaction_mode' => 'DEFERRED',
        ]);

        // The legacy fuelcontrol base tables live in a nested migration
        // directory. Register it only for tests so later root migrations that
        // alter those tables can run against the isolated SQLite connection.
        $app->make('migrator')->path(base_path('tests/Fixtures/migrations'));
        $app->make('migrator')->path(database_path('migrations/fuelcontrol'));

        return $app;
    }
}
