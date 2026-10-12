<?php

declare(strict_types=1);

namespace GavG\TransducibleCollections\Tests;

use GavG\TransducibleCollections\TransducibleCollectionsServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app): array
    {
        return [
            TransducibleCollectionsServiceProvider::class,
        ];
    }
}
