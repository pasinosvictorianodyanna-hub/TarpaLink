<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->forgetStaleViteHotFile();
    }

    /**
     * `public/hot` is Vite's "a dev server is running" marker. `npm run dev`
     * writes it and deletes it on a clean exit, but a killed dev server leaves
     * it behind — and then every Blade page points its assets at a port nothing
     * is listening on.
     *
     * The application is right to obey the file. A test suite is not a browser:
     * during a test run it can only be stale, and leaving it in place makes
     * ConsoleTest fail on the developer's machine while passing in CI, which is
     * the worst way round for a failure to behave.
     */
    private function forgetStaleViteHotFile(): void
    {
        $hot = public_path('hot');

        if (is_file($hot)) {
            @unlink($hot);
        }
    }
}
