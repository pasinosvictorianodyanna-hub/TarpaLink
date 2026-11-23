<?php

namespace Tests\Feature;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The customer's header.
 *
 * The bar used to offer three doors and left four of the customer's seven
 * screens reachable only by walking into a shop or a job. All seven now sit in
 * the bar. These tests hold that to the two things that matter: every item goes
 * somewhere real, and an item with nowhere to go is visibly not a link rather
 * than a link that quietly does the wrong thing.
 */
class CustomerNavTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    private function customer(string $email = 'jomar@tarpalink.ph'): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    /**
     * The header's link list on its own.
     *
     * The same screen names appear elsewhere on the page — in a job row, in the
     * footer — so a bare assertSee over the whole document would pass whether or
     * not the bar actually carried the item.
     */
    private function bar(string $html): string
    {
        preg_match('/<div class="appbar-links">(.*?)<\/div>/s', $html, $match);

        return $match[1] ?? '';
    }

    /** The href of a link in the bar, or null when the item is not a link. */
    private function linkFor(string $bar, string $label): ?string
    {
        preg_match('/<a href="([^"]+)"[^>]*>'.preg_quote($label, '/').'<\/a>/', $bar, $match);

        return $match[1] ?? null;
    }

    /** True when the item is rendered as the dimmed, non-clickable variant. */
    private function isOff(string $bar, string $label): bool
    {
        return (bool) preg_match(
            '/<span class="nav-off"[^>]*>'.preg_quote($label, '/').'<\/span>/',
            $bar
        );
    }

    public function test_the_bar_lists_all_seven_screens(): void
    {
        $bar = $this->bar($this->actingAs($this->customer())->get('/account')->assertOk()->getContent());

        foreach ([
            'Shop directory',
            'Shop profile',
            'Order builder',
            'Checkout',
            'Order status',
            'My jobs',
            'Profile',
        ] as $screen) {
            $this->assertMatchesRegularExpression(
                '/'.preg_quote($screen, '/').'/',
                $bar,
                "{$screen} is missing from the header"
            );
        }
    }

    public function test_order_status_becomes_a_link_once_there_is_a_job(): void
    {
        $me = $this->customer();

        $bar = $this->bar($this->actingAs($me)->get('/account')->assertOk()->getContent());

        // Which job is deliberately not pinned here: the bar resumes the most
        // useful unfinished one, and a test that names a specific row turns any
        // change to that ranking into a failure about the ranking rather than
        // about the link. What must hold is that it goes somewhere real and
        // that it is the customer's own work.
        $link = $this->linkFor($bar, 'Order status');

        $this->assertNotNull($link, 'Order status should be a link once the customer has a job');
        $this->assertTrue(
            $me->jobs()->get()->contains(fn ($job) => route('jobs.show', $job) === $link),
            'Order status should point at one of the customer\'s own jobs.',
        );
        $this->assertFalse($this->isOff($bar, 'Order status'));
    }

    public function test_the_resumed_job_is_the_newest_when_two_were_placed_at_once(): void
    {
        $me = $this->customer();

        // Two jobs in the same second is not a contrived case: the seeder
        // produces them, and so does anybody who places an order and then
        // corrects it. Without an id tie-break the database picks arbitrarily,
        // so the chip pointed at one job and then the other on consecutive
        // requests.
        $quoted = $me->jobs()->where('status', 'quoted')->orderByDesc('id')->get();

        $this->assertGreaterThanOrEqual(2, $quoted->count(), 'This test needs two quoted jobs to compare');

        $newest = $quoted->first();
        $other = $quoted->last();

        $newest->forceFill(['created_at' => '2026-01-01 09:00:00'])->save();
        $other->forceFill(['created_at' => '2026-01-01 09:00:00'])->save();

        $bar = $this->bar($this->actingAs($me)->get('/account')->assertOk()->getContent());

        $this->assertSame(route('jobs.show', $newest), $this->linkFor($bar, 'Order status'));
    }

    public function test_a_customer_with_no_jobs_sees_order_status_dimmed_not_linked(): void
    {
        $fresh = User::create([
            'name' => 'New Customer',
            'email' => 'new.customer@tarpalink.ph',
            'password' => 'password',
            'role' => 'customer',
        ]);

        $html = $this->actingAs($fresh)->get('/account')->assertOk()->getContent();
        $bar = $this->bar($html);

        // Visible, because it is part of the journey — but not a link, because
        // there is no job to open.
        $this->assertTrue($this->isOff($bar, 'Order status'));
        $this->assertNull($this->linkFor($bar, 'Order status'));
        $this->assertStringContainsString('No order in progress', $bar);
        $this->assertStringNotContainsString('resume-chip', $html);
    }

    public function test_screens_that_need_a_shop_are_dimmed_with_the_reason(): void
    {
        $bar = $this->bar($this->actingAs($this->customer())->get('/account')->assertOk()->getContent());

        foreach (['Shop profile', 'Order builder'] as $screen) {
            $this->assertTrue($this->isOff($bar, $screen), "{$screen} should not be a link on My jobs");
        }

        $this->assertTrue($this->isOff($bar, 'Checkout'));
        $this->assertStringContainsString('Open a shop first', $bar);
        // Two different reasons, because they lead to two different actions:
        // no shop yet, versus a shop but no size chosen. "Build an order first"
        // covered both and told the customer nothing they could act on.
        $this->assertStringContainsString('Set your size first', $bar);
    }

    public function test_the_bar_points_at_the_shop_the_customer_is_looking_at(): void
    {
        $shop = Shop::active()->firstOrFail();

        $bar = $this->bar(
            $this->actingAs($this->customer())->get('/shops/'.$shop->slug)->assertOk()->getContent()
        );

        $this->assertSame(route('shops.show', $shop), $this->linkFor($bar, 'Shop profile'));
        $this->assertSame(route('shops.builder', $shop), $this->linkFor($bar, 'Order builder'));
        $this->assertStringNotContainsString('Open a shop first', $bar);
    }

    /**
     * Checkout prices a spec. With no spec on the URL it opens at the shop's
     * default size and quantity — a real price for an order nobody configured.
     * The bar used to offer it anyway, so "Checkout" on a shop page led to a
     * phantom 3×4 ft job at the shop's default rate.
     */
    public function test_checkout_is_dimmed_until_a_size_has_been_chosen(): void
    {
        $shop = Shop::active()->firstOrFail();

        $onProfile = $this->bar(
            $this->actingAs($this->customer())->get('/shops/'.$shop->slug)->assertOk()->getContent()
        );

        $this->assertTrue($this->isOff($onProfile, 'Checkout'));
        $this->assertStringContainsString('Set your size first', $onProfile);

        $onBuilder = $this->bar(
            $this->actingAs($this->customer())->get('/shops/'.$shop->slug.'/build')->assertOk()->getContent()
        );

        $this->assertSame(route('checkout.show', $shop), $this->linkFor($onBuilder, 'Checkout'));
    }

    /**
     * "Order status" pointed at the most urgent job even while the customer was
     * reading a different one, so a highlighted item moved them somewhere else.
     */
    public function test_order_status_points_at_the_job_being_viewed(): void
    {
        $customer = $this->customer();
        $jobs = $customer->jobs()->with('shop')->take(2)->get();

        $this->assertGreaterThanOrEqual(2, $jobs->count(), 'The seed needs two jobs for this customer.');

        [$viewing, $other] = [$jobs[0], $jobs[1]];

        $bar = $this->bar(
            $this->actingAs($customer)->get(route('jobs.show', $viewing))->assertOk()->getContent()
        );

        $this->assertSame(route('jobs.show', $viewing), $this->linkFor($bar, 'Order status'));
        $this->assertNotSame(route('jobs.show', $other), $this->linkFor($bar, 'Order status'));
    }

    public function test_the_chip_names_the_proof_waiting_on_the_customer(): void
    {
        $me = $this->customer();
        $job = $me->jobs()->firstOrFail();
        $job->update(['status' => 'awaiting_proof']);

        $html = $this->actingAs($me)->get('/account')->assertOk()->getContent();

        $this->assertStringContainsString('Approve proof', $html);
        $this->assertStringContainsString('resume-chip urgent', $html);
        $this->assertStringContainsString(route('jobs.show', $job), $html);
    }

    public function test_a_proof_outranks_a_newer_unpaid_order(): void
    {
        $me = $this->customer();

        // Clear the board, then put a proof behind a newer unpaid order. The chip
        // should still be the proof: it is the only one of the two that needs the
        // customer to do something.
        $me->jobs()->update(['status' => 'collected']);

        $jobs = $me->jobs()->get(); // newest first
        $unpaid = $jobs[0];
        $proof = $jobs[1];

        // Distinct timestamps on purpose. `User::jobs()` carries a `latest()`,
        // so a ranking that is appended rather than applied becomes a tie-break
        // — and this test passed for months on the seeded database only because
        // several seeded jobs share a `created_at` second. Forcing the unpaid
        // order to be unambiguously newer is what makes the ranking prove itself.
        $unpaid->forceFill(['created_at' => '2026-06-02 09:00:00'])->save();
        $proof->forceFill(['created_at' => '2026-06-01 09:00:00'])->save();

        $unpaid->update(['status' => 'quoted']);
        $proof->update(['status' => 'awaiting_proof']);

        $html = $this->actingAs($me)->get('/account')->assertOk()->getContent();

        $this->assertStringContainsString('Approve proof', $html);
        $this->assertStringContainsString(route('jobs.show', $proof), $html);
        $this->assertStringNotContainsString('Unpaid '.$unpaid->reference, $html);
    }

    public function test_an_unpaid_order_names_the_move_its_page_can_make(): void
    {
        $me = $this->customer();

        // The newest unpaid order, because that is the one the bar resumes: the
        // ranking is status first and then newest, with the id as the tie-break.
        $job = $me->jobs()->where('status', 'quoted')->orderByDesc('id')->firstOrFail();

        // A quoted job with no file is stuck on the upload, so state the
        // precondition rather than inheriting it from the seeder.
        $job->artworks()->delete();
        $job->unsetRelation('artworks');

        $html = $this->actingAs($me)->get('/account')->assertOk()->getContent();

        // No route charges an existing job, so neither "Finish" nor "Unpaid" is
        // a move the destination can perform — both name a payment the job page
        // cannot take. The chip names the upload instead, in the same words the
        // row button on this page already uses.
        $this->assertStringContainsString('Send design '.$job->reference, $html);
        $this->assertStringNotContainsString('Unpaid '.$job->reference, $html);
        $this->assertStringNotContainsString('Finish '.$job->reference, $html);
    }

    public function test_the_seven_screens_belong_to_customers_only(): void
    {
        $owner = User::where('email', 'kevin@inkwell.ph')->firstOrFail();
        $admin = User::where('email', 'sofia@tarpalink.ph')->firstOrFail();

        foreach ([$owner, $admin] as $user) {
            $bar = $this->bar($this->actingAs($user)->get('/account')->assertOk()->getContent());

            $this->assertStringNotContainsString(
                'Shop directory',
                $bar,
                "{$user->email} should not be offered the customer journey"
            );
            $this->assertStringNotContainsString('nav-off', $bar);
        }
    }

    public function test_the_bar_marks_the_screen_the_customer_is_on(): void
    {
        $bar = $this->bar($this->actingAs($this->customer())->get('/account')->assertOk()->getContent());

        // Exactly one item carries the active class, and it is My jobs.
        $this->assertSame(1, substr_count($bar, 'class="on"'));
        $this->assertMatchesRegularExpression('/<a href="[^"]+" class="on">My jobs<\/a>/', $bar);
    }
}
