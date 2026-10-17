<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();
        $connection = $app['config']->get('database.default');
        $database = $app['config']->get('database.connections.'.$connection.'.database');
        if (! (($connection === 'sqlite' && $database === ':memory:') || ($connection === 'mysql' && $database === 'badminton_hub_codex_test_20261008'))) {
            throw new \RuntimeException('Kiểm thử chỉ được chạy trên SQLite trong bộ nhớ hoặc database MySQL dành riêng cho kiểm thử.');
        }

        return $app;
    }
}
