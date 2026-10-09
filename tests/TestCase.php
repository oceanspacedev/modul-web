<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\Support\TestDatabaseSeeder;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected $seeder = TestDatabaseSeeder::class;

    /**
     * Creates the application and refuses to run against anything but an
     * isolated in-memory SQLite database (tests rebuild the schema).
     */
    public function createApplication(): Application
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        if (! $app->environment('testing')
            || $app['config']->get('database.default') !== 'sqlite'
            || $app['config']->get('database.connections.sqlite.database') !== ':memory:'
            || $app['config']->get('database.connections.sqlite.url')) {
            throw new RuntimeException('Tests require an isolated in-memory SQLite database.');
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        // Point the WhatsApp gateway at a faked host so no real request can leave.
        config([
            'services.whatsapp.url' => 'https://whatsapp.test',
            'services.whatsapp.token' => 'test-only-token',
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'whatsapp.test/*' => Http::response(['status' => true], 200),
        ]);
    }
}
