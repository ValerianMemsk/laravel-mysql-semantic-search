<?php

namespace ValerianMemsk\SemanticSearch\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use ValerianMemsk\SemanticSearch\SemanticSearchServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            SemanticSearchServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('semantic-search.database_driver', 'mariadb');
        $app['config']->set('semantic-search.auto_embed_on_save', false);
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }
}
