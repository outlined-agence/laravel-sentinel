<?php

declare(strict_types=1);

namespace Outlined\Sentinel\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Outlined\Sentinel\SentinelServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app): array
    {
        return [
            SentinelServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Setup default database for testing
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Disable Slack/Discord for tests by default
        $app['config']->set('sentinel.slack.enabled', false);
        $app['config']->set('sentinel.discord.enabled', false);
        $app['config']->set('sentinel.sentry.enabled', false);

        // Enable database for tests
        $app['config']->set('sentinel.database.enabled', true);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
