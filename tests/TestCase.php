<?php

declare(strict_types=1);

namespace Beziworld\InPost\Tests;

use Beziworld\InPost\InPostServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [InPostServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('inpost.token', 'shipx-token');
        $app['config']->set('inpost.organization_id', '1234');
        $app['config']->set('inpost.sandbox', true);
        $app['config']->set('inpost.geowidget_token', 'geo-token');
    }
}
