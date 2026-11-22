<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The shop and its customers are in the Philippines. A due date of 17:00 has
 * to be 5 PM in Manila — under UTC the same row meant 1 AM the following day,
 * and "due today" ended at 8 AM local time.
 */
class TimezoneTest extends TestCase
{
    public function test_the_application_clock_is_manila_time(): void
    {
        $this->assertSame('Asia/Manila', config('app.timezone'));

        $due = now()->addDays(2)->setTime(17, 0);

        $this->assertSame('Asia/Manila', $due->timezoneName);
        $this->assertSame('17:00', $due->format('H:i'));
        $this->assertSame('+08:00', $due->format('P'));
    }

    public function test_mysql_sessions_use_the_matching_utc_offset(): void
    {
        // SQLite ignores the connection timezone, but MySQL TIMESTAMP columns
        // convert through it. If the two drift, the wall clock above comes back
        // eight hours out on the deployed database.
        $this->assertSame('+08:00', config('database.connections.mysql.timezone'));
        $this->assertSame(
            config('app.timezone') === 'Asia/Manila',
            config('database.connections.mysql.timezone') === '+08:00',
        );
    }
}
