<?php

declare(strict_types=1);

namespace Rfpdl\WhatsUpDoc\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Rfpdl\WhatsUpDoc\WhatsUpDocServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            WhatsUpDocServiceProvider::class,
        ];
    }

    protected function getFixturePath(string $relative = ''): string
    {
        return __DIR__ . '/Fixtures' . ($relative ? '/' . ltrim($relative, '/') : '');
    }
}
