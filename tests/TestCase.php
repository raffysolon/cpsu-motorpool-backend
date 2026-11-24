<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $this->traitsUsedByTest = array_flip(class_uses_recursive(static::class));
        $app = require dirname(__DIR__).'/bootstrap/app.php';
        $app->afterBootstrapping(LoadConfiguration::class, function ($app) {
            $config = $app['config'];
            if ($app->configurationIsCached()
                || $config->get('app.env') !== 'testing'
                || $config->get('database.default') !== 'sqlite'
                || $config->get('database.connections.sqlite.database') !== ':memory:'
                || $config->get('database.connections.sqlite.url')) {
                throw new \RuntimeException('Unsafe test database configuration.');
            }
            // Explicit named pgsql/mysql connections must fail, not reach a server.
            $config->set('database.connections', ['sqlite' => $config->get('database.connections.sqlite')]);
        });
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        Http::preventStrayRequests();
        return $app;
    }
}
