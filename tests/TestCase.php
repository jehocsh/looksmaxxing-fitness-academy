<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();
        $connection = $app['config']->get('database.default');
        $database = $app['db']->connection()->getDatabaseName();
        if ($connection !== 'mysql' || $database !== 'looksmaxxing_testing') {
            throw new RuntimeException('Tests require the separate looksmaxxing_testing MySQL database.');
        }

        return $app;
    }
}
