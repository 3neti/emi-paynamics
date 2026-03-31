<?php

namespace LBHurtado\EmiPaynamicsConstellation\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use LBHurtado\EmiCore\EmiCoreServiceProvider;
use LBHurtado\EmiPaynamicsConstellation\ConstellationServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'LBHurtado\\EmiPaynamicsConstellation\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app): array
    {
        return [
            EmiCoreServiceProvider::class,
            ConstellationServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        config()->set('constellation.base_url', 'https://asterism.payserv.net/v1');
        config()->set('constellation.username', 'test-user');
        config()->set('constellation.password', 'test-pass');
        config()->set('constellation.merchant_key', 'TEST_MERCHANT_KEY');

        // Run emi-core migrations
        $coreMigrations = glob(__DIR__.'/../vendor/3neti/emi-core/database/migrations/*.php');

        foreach ($coreMigrations as $migration) {
            (include $migration)->up();
        }

        // Run constellation migrations
        $constellationMigrations = glob(__DIR__.'/../database/migrations/*.php');
        foreach ($constellationMigrations as $migration) {
            (include $migration)->up();
        }
    }
}
