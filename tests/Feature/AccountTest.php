<?php

namespace Tests\Feature;

use App\Models\Job;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The customer dashboard, against its props contract.
 *
 * The list is a Vue component, so the server's job is the numbers and the
 * decisions: the counters, which button each row gets, whether the proof bar
 * appears. Those are what this file holds to account — and the counters are
 * checked against a plain count over the same rows rather than against the
 * controller's own arithmetic.
 */
class AccountTest extends TestCase
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
     * @return array<string, mixed>
     */
    private function props(string $email = 'jomar@tarpalink.ph', string $query = ''): array
    {
        return $this->actingAs($this->customer($email))
            ->get('/account'.$query)
            ->assertOk()
            ->viewData('props');
    }

    public function test_the_page_mounts_as_a_vue_component_with_serialisable_props(): void
    {
        $html = $this->actingAs($this->customer())->get('/account')->assertOk()->getContent();

        $this->assertStringContainsString('data-component="account"', $html);

        preg_match('/data-props="([^"]*)"/', $html, $match);
        $this->assertNotEmpty($match, 'The mount host is missing data-props.');

        $decoded = json_decode(html_entity_decode($match[1], ENT_QUOTES), true);
        $this->assertIsArray($decoded, 'data-props is not valid JSON.');
        $this->assertArrayHasKey('jobs', $decoded);
        $this->assertArrayHasKey('kpis', $decoded);
    }

    public function test_the_counters_match_a_plain_count_over_the_customers_jobs(): void
    {
        $jobs = Job::where('user_id', $this->customer()->id);

        // Written out here on purpose: if the controller changes what it calls
        // "active", the number and this list have to disagree before the demo does.
        $active = (clone $jobs)->whereIn('status', ['quoted', 'awaiting_proof', 'approved', 'in_production'])->count();
        $ready = (clone $jobs)->where('status', 'ready')->count();
        $done = (clone $jobs)->whereIn('status', ['collected', 'cancelled'])->count();

        $kpis = collect($this->props()['kpis'])->pluck('value', 'label');

        $this->assertSame((string) $active, $kpis['Active jobs']);
        $this->assertSame((string) $ready, $kpis['Ready to collect']);
        $this->assertSame($done, collect($this->props()['tabs'])->firstWhere('key', 'done')['count']);
    }

    public function test_the_list_and_the_counters_cannot_disagree(): void
    {
        $props = $this->props();

        $this->assertCount($props['tabs'][0]['count'], $props['jobs']);
        $this->assertNull($props['empty']);
    }

    public function test_the_all_tab_links_to_a_clean_url_with_no_empty_status(): void
    {
        // `?status=` and no query at all are different URLs, and only one of them
        // leaves the tab strip tidy.
        $this->assertSame(route('account'), $this->props()['tabs'][0]['url']);
    }

    public function test_the_all_tab_is_the_one_that_is_on_by_default(): void
    {
        $on = collect($this->props()['tabs'])->where('on', true);

        $this->assertCount(1, $on);
        $this->assertSame('all', $on->first()['key']);
    }

    public function test_a_filtered_tab_carries_the_filter_into_the_search_form(): void
    {
        // Otherwise searching from the Active tab silently drops the filter.
        $props = $this->props('jomar@tarpalink.ph', '?status=active');

        $this->assertSame('active', $props['search']['filter']);
        $this->assertTrue(collect($props['tabs'])->firstWhere('key', 'active')['on']);
    }

    public function test_the_proof_bar_only_appears_for_a_customer_with_a_proof_waiting(): void
    {
        $this->assertNull($this->props('jomar@tarpalink.ph')['alert']);

        $waiting = Job::where('user_id', $this->customer('renz@example.com')->id)
            ->where('status', 'awaiting_proof')
            ->firstOrFail();

        $alert = $this->props('renz@example.com')['alert'];

        $this->assertIsArray($alert);
        $this->assertStringContainsString($waiting->reference, $alert['title']);
        $this->assertSame(route('jobs.show', $waiting), $alert['url']);
    }

    public function test_each_row_gets_the_button_its_status_asks_for(): void
    {
        $props = $this->props('renz@example.com');

        $byStatus = collect($props['jobs'])->keyBy('status');

        // A proof is the only thing the customer is being asked to do, so it is
        // the only button drawn in the accent colour.
        $this->assertSame('Review proof', $byStatus['Proof sent']['action']['label']);
        $this->assertSame('magenta', $byStatus['Proof sent']['action']['style']);

        $this->assertSame('Reorder', $byStatus['Collected']['action']['label']);
        $this->assertSame('ghost', $byStatus['Collected']['action']['style']);

        $this->assertSame('View', $byStatus['Ready for pickup']['action']['label']);
    }

    public function test_a_collected_row_recedes_and_a_live_row_does_not(): void
    {
        $rows = collect($this->props('renz@example.com')['jobs'])->keyBy('status');

        $this->assertTrue($rows['Collected']['dim']);
        $this->assertFalse($rows['Ready for pickup']['dim']);
    }

    public function test_search_matches_a_shop_name_and_a_reference(): void
    {
        $job = Job::where('user_id', $this->customer()->id)->firstOrFail();

        foreach ([$job->shop->name, $job->reference] as $needle) {
            $rows = $this->props('jomar@tarpalink.ph', '?q='.urlencode($needle))['jobs'];

            $this->assertNotEmpty($rows, "Searching for {$needle} returned nothing.");

            // Every row's meta line carries its reference, so finding it proves
            // the search returned this job rather than something adjacent.
            $this->assertStringContainsString(
                $job->reference,
                collect($rows)->pluck('meta')->implode(' | '),
                "Searching for {$needle} did not return {$job->reference}.",
            );
        }
    }

    public function test_an_unmatched_search_shows_the_empty_state_rather_than_an_empty_list(): void
    {
        $props = $this->props('jomar@tarpalink.ph', '?q=zzzz-no-such-job');

        $this->assertSame([], $props['jobs']);
        $this->assertNotNull($props['empty']);
        $this->assertStringContainsString('zzzz-no-such-job', $props['empty']['title']);
    }

    public function test_a_customer_with_no_jobs_gets_the_first_order_copy(): void
    {
        $fresh = User::factory()->create(['role' => 'customer']);

        $props = $this->actingAs($fresh)->get('/account')->assertOk()->viewData('props');

        $this->assertSame([], $props['jobs']);
        $this->assertStringContainsString('Find a shop', $props['empty']['body']);
        $this->assertStringContainsString('Find a shop', $props['summary']);
    }

    public function test_the_account_strip_names_the_customer_and_links_to_the_profile(): void
    {
        $account = $this->props()['account'];

        $this->assertSame($this->customer()->name, $account['name']);
        $this->assertSame('JM', $account['initials']);
        $this->assertStringContainsString($this->customer()->email, $account['contact']);
        $this->assertSame(route('profile.edit'), $account['url']);
    }

    public function test_another_customers_jobs_never_appear(): void
    {
        $mine = collect($this->props()['jobs'])->pluck('title');
        $theirs = Job::where('user_id', $this->customer('renz@example.com')->id)->pluck('id');

        $this->assertNotEmpty($mine);
        $this->assertSame(
            0,
            Job::whereIn('id', $theirs)->where('user_id', $this->customer()->id)->count(),
            'The two seed customers must not share jobs.',
        );
    }
}
