<?php

namespace Tests\Feature\GovStore\Navigation;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase;

/**
 * Deliberately uses only an in-memory SQLite database (like the other gov-store tests),
 * never the application's database. These tests read registrations, lang files and the
 * layout; they need no schema.
 */
abstract class NavigationTestCase extends TestCase
{
    public function createApplication()
    {
        foreach (['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'LOG_CHANNEL' => 'stderr', 'CACHE_DRIVER' => 'array', 'SESSION_DRIVER' => 'array'] as $key => $value) {
            putenv($key.'='.$value);
            $_ENV[$key] = $_SERVER[$key] = $value;
        }
        $app = require __DIR__.'/../../../../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }
}
