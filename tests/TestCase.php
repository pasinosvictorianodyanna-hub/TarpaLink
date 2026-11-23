<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

/**
 * Nothing here, deliberately.
 *
 * #98 added a `setUp()` that deleted `public/hot` before every test, to stop a
 * killed dev server from turning ConsoleTest red. It worked, and it was wrong:
 * the file is also written by a dev server that is *running*, so `php artisan
 * test` next to `npm run dev` deleted a live marker — the browser then quietly
 * fell back to `public/build` and rendered the last build instead of the working
 * tree. That is a worse version of the bug it fixed, and it cost a round of
 * screenshots before it was noticed.
 *
 * The test was asking the wrong question. It asserted a build path when what it
 * meant was "the console loads its own stylesheet", which is true in both modes.
 * With the assertion fixed, nothing has to be deleted.
 */
abstract class TestCase extends BaseTestCase
{
    //
}
