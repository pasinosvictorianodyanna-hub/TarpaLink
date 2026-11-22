<?php

namespace Tests\Feature;

use App\Models\Job;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The customer's header.
 *
 * The bar used to offer three doors and left four of the customer's seven
 * screens reachable only by walking into a shop or a job. These tests hold the
 * replacement to the two things that matter: the links go somewhere real, and
 * nothing promises an action the destination cannot deliver.
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
     * "Order status" and the other six names also appear in the More menu, so a
     * bare assertSee over the whole page would pass whether or not the bar
     * itself offered the link.
     */
    private function bar(string $html): string
    {
        preg_match('/<div class="appbar-links">(.*?)<\/div>/s', $html, $match);

        return $match[1] ?? '';
    }

    private function menu(string $html): string
    {
        preg_match('/<div class="more-panel">(.*?)<\/details>/s', $html, $match);

        return $match[1] ?? '';
    }

    public function test_the_bar_offers_order_status_once_there_is_a_job(): void
    {
        $me = $this->customer();
        $job = $me->jobs()->firstOrFail();

        $html = $this->actingAs($me)->get('/account')->assertOk()->getContent();

        $this->assertStringContainsString('Order status', $this->bar($html));
        $this->assertStringContainsString(route('jobs.show', $job), $this->bar($html));
    }

    public function test_a_customer_with_no_jobs_is_not_offered_a_dead_order_status_link(): void
    {
        $fresh = User::create([
            'name' => 'New Customer',
            'email' => 'new.customer@tarpalink.ph',
            'password' => 'password',
            'role' => 'customer',
        ]);

        $html = $this->actingAs($fresh)->get('/account')->assertOk()->getContent();

        $this->assertStringNotContainsString('Order status', $this->bar($html));
        $this->assertStringNotContainsString('resume-chip', $html);
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

        $unpaid->update(['status' => 'quoted']);
        $proof->update(['status' => 'awaiting_proof']);

        $html = $this->actingAs($me)->get('/account')->assertOk()->getContent();

        $this->assertStringContainsString('Approve proof', $html);
        $this->assertStringContainsString(route('jobs.show', $proof), $html);
        $this->assertStringNotContainsString('Unpaid '.$unpaid->reference, $html);
    }

    public function test_an_unpaid_order_is_named_rather_than_promised(): void
    {
        $me = $this->customer();
        $job = $me->jobs()->where('status', 'quoted')->firstOrFail();

        $html = $this->actingAs($me)->get('/account')->assertOk()->getContent();

        // The job page has no way to take a payment, so the chip must not say
        // "Finish" — that would be a promise the destination cannot keep.
        $this->assertStringContainsString('Unpaid '.$job->reference, $html);
        $this->assertStringNotContainsString('Finish '.$job->reference, $html);
    }

    public function test_the_menu_lists_all_seven_screens(): void
    {
        $html = $this->actingAs($this->customer())->get('/account')->assertOk()->getContent();
        $menu = $this->menu($html);

        $this->assertNotSame('', $menu, 'The More menu is missing from the header');

        $screens = [
            '01' => 'Shop directory',
            '02' => 'Shop profile',
            '03' => 'Order builder',
            '04' => 'Checkout',
            '05' => 'Order status',
            '06' => 'My jobs',
            '07' => 'Profile',
        ];

        foreach ($screens as $number => $name) {
            $this->assertStringContainsString('>'.$number.'</span>', $menu, "Screen {$number} is missing");
            $this->assertStringContainsString($name, $menu);
        }
    }

    public function test_screens_that_need_a_shop_say_so_instead_of_guessing(): void
    {
        $me = $this->customer();

        $menu = $this->menu($this->actingAs($me)->get('/account')->assertOk()->getContent());

        // On My jobs there is no shop in the URL, so builder and checkout cannot
        // be offered — and they must not silently point at the directory either.
        $this->assertStringContainsString('Open a shop first', $menu);
        $this->assertStringContainsString('Build an order first', $menu);
        $this->assertStringContainsString('more-item off', $menu);
    }

    public function test_the_menu_points_at_the_shop_the_customer_is_looking_at(): void
    {
        $shop = Shop::active()->firstOrFail();

        $menu = $this->menu(
            $this->actingAs($this->customer())->get('/shops/'.$shop->slug)->assertOk()->getContent()
        );

        $this->assertStringContainsString(route('shops.show', $shop), $menu);
        $this->assertStringContainsString(route('shops.builder', $shop), $menu);
        $this->assertStringContainsString(route('checkout.show', $shop), $menu);
        $this->assertStringNotContainsString('Open a shop first', $menu);
    }

    public function test_the_menu_belongs_to_customers_only(): void
    {
        $owner = User::where('email', 'kevin@inkwell.ph')->firstOrFail();
        $admin = User::where('email', 'sofia@tarpalink.ph')->firstOrFail();

        foreach ([$owner, $admin] as $user) {
            $html = $this->actingAs($user)->get('/account')->assertOk()->getContent();

            $this->assertStringNotContainsString(
                'appbar-more',
                $html,
                "{$user->email} should not be offered the customer screen menu"
            );
        }
    }

    public function test_the_menu_marks_the_screen_the_customer_is_on(): void
    {
        $me = $this->customer();

        $menu = $this->menu($this->actingAs($me)->get('/account')->assertOk()->getContent());

        $this->assertStringContainsString('more-item on', $menu);
        $this->assertStringContainsString('You are here', $menu);
    }
}
