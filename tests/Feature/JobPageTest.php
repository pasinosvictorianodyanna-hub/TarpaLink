<?php

namespace Tests\Feature;

use App\Models\Job;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What the job page promises.
 *
 * The Progress card prints one line about when the work will be ready, and that
 * line is the only date a customer sees. It used to print "Est. ready <date>"
 * for every job that was not collected or cancelled — including a quote, which
 * has not been paid for, has no artwork, and has not been accepted by the shop.
 * `due_at` on a quote is the date checkout pencilled in, so the page was
 * presenting a commitment nobody had made, next to the shop's own published
 * turnaround and sometimes days away from it.
 */
class JobPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    private function customer(): User
    {
        return User::where('email', 'jomar@tarpalink.ph')->firstOrFail();
    }

    private function quotedJob(): Job
    {
        return Job::where('status', 'quoted')
            ->where('user_id', $this->customer()->id)
            ->firstOrFail();
    }

    public function test_a_quote_promises_a_turnaround_rather_than_a_date(): void
    {
        $job = $this->quotedJob();

        // The whole page, not one card. The Progress header was corrected first
        // and the Pickup details row kept printing the same date two inches
        // away, so the two disagreed in one viewport.
        $this->actingAs($this->customer())
            ->get(route('jobs.show', $job))
            ->assertOk()
            ->assertSee('once you pay and send your design')
            ->assertSee('Not scheduled yet')
            ->assertDontSee('Est. ready')
            ->assertDontSee($job->due_at->format('D, g:i A'))
            ->assertDontSee($job->due_at->format('D, d M · g:i A'));
    }

    public function test_a_quote_has_no_date_on_a_step_it_has_not_reached(): void
    {
        // The rail's own timestamp, which the page assertions above cannot
        // isolate: the seeded quote is placed and due in the same minute, so
        // "23 Nov · 7:32 PM" appears legitimately beside "Order placed".
        $ready = collect($this->quotedJob()->progressSteps())
            ->firstWhere('label', 'Ready for pickup');

        $this->assertNotNull($ready, 'The rail should still show the pickup step.');
        $this->assertNull($ready['at'], 'A quote shows a pickup date it has not been given.');
    }

    public function test_the_promise_uses_the_shops_own_number(): void
    {
        $job = $this->quotedJob();

        $this->actingAs($this->customer())
            ->get(route('jobs.show', $job))
            ->assertOk()
            ->assertSee($job->shop->turnaroundPhrase());
    }

    public function test_a_job_in_the_queue_shows_its_own_date(): void
    {
        $job = Job::whereIn('status', ['in_production', 'ready'])
            ->where('user_id', $this->customer()->id)
            ->whereNotNull('due_at')
            ->firstOrFail();

        $this->actingAs($this->customer())
            ->get(route('jobs.show', $job))
            ->assertOk()
            ->assertSee('Est. ready '.$job->due_at->format('D, d M · g:i A'));
    }

    public function test_a_collected_job_stops_talking_about_readiness(): void
    {
        $job = Job::where('status', 'collected')
            ->where('user_id', $this->customer()->id)
            ->firstOrFail();

        $this->actingAs($this->customer())
            ->get(route('jobs.show', $job))
            ->assertOk()
            ->assertDontSee('Est. ready')
            ->assertDontSee('once you pay and send your design');
    }
}
