<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        $connection = $app['config']['database.default'];
        $database = $app['config']["database.connections.{$connection}"];

        if (! $app->environment('testing') || ($database['driver'] ?? null) !== 'sqlite' || ($database['database'] ?? null) !== ':memory:') {
            throw new RuntimeException('Pengujian dihentikan: database harus SQLite :memory: agar data aplikasi tetap aman.');
        }

        return $app;
    }
}
