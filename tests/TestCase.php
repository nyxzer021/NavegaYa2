<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        $name = (string) $app['config']->get('database.default');
        $database = (string) $app['config']->get('database.connections.'.$name.'.database');
        $memory = $database === ':memory:' || str_contains($database, 'mode=memory');
        if (! $memory) {
            throw new \RuntimeException('Las pruebas iban a usar '.$database.'. Deben usar sqlite :memory: para no tocar la base de desarrollo.');
        }

        return $app;
    }
}
