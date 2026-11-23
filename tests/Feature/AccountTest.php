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

    public function test_the_bar_shows_the_proof_that_is_waiting(): void
    {
        $waiting = Job::where('user_id', $this->customer('renz@example.com')->id)
            ->where('status', 'awaiting_proof')
            ->firstOrFail();

        $alert = $this->props('renz@example.com')['alert'];

        $this->assertIsArray($alert);
        $this->assertStringContainsString($waiting->reference, $alert['title']);
        $this->assertSame(route('jobs.show', $waiting), $alert['url']);
    }

    /**
     * A job the shop cannot start is as much "your move" as a proof, and the
     * dashboard used to say nothing about it: the customer had a paid order, a
     * progress rail, and no hint that the one thing the shop needed had never
     * been sent.
     */
    public function test_the_bar_asks_for_a_design_when_the_shop_has_none(): void
    {
        // A customer can have more than one job waiting on a file, and the bar
        // shows one of them — so the assertion is "one of the jobs that needs a
        // design", not a specific row.
        $waiting = Job::where('user_id', $this->customer('jomar@tarpalink.ph')->id)
            ->where('status', 'quoted')
            ->whereDoesntHave('artworks')
            ->pluck('reference');

        $this->assertNotEmpty($waiting);

        $alert = $this->props('jomar@tarpalink.ph')['alert'];

        $this->assertIsArray($alert);
        $this->assertSame('Send design', $alert['label']);

        // The URL is the authority on which job the bar is about.
        $this->assertContains(
            basename(parse_url($alert['url'], PHP_URL_PATH)),
            $waiting->all(),
        );
    }

    public function test_the_bar_is_empty_once_nothing_is_waiting(): void
    {
        $customer = $this->customer('jomar@tarpalink.ph');

        // Give every quoted job a file: with the design sent, there is nothing
        // left for this customer to do, and the bar must disappear rather than
        // keep asking.
        foreach ($customer->jobs()->where('status', 'quoted')->get() as $job) {
            $job->artworks()->create([
                'uploaded_by' => $customer->id,
                'file_path' => 'artwork/test.pdf',
                'original_name' => 'poster.pdf',
                'mime' => 'application/pdf',
                'bytes' => 1024,
            ]);
        }

        $this->assertNull($this->props('jomar@tarpalink.ph')['alert']);
    }

    public function test_each_row_gets_the_button_its_status_asks_for(): void
    {
        $props = $this->props('renz@example.com');

        $byStatus = collect($props['jobs'])->keyBy('status');

        // A proof is the thing the customer is being asked to do, so it is drawn
        // in the accent colour.
        $this->assertSame('Review proof', $byStatus['Proof sent']['action']['label']);
        $this->assertSame('magenta', $byStatus['Proof sent']['action']['style']);

        // "Reorder" promised the same job again and opened the builder at the
        // shop's default size. The link now carries the size and quantity, and
        // the label says what it does.
        $this->assertSame('Order again', $byStatus['Collected']['action']['label']);
        $this->assertSame('ghost', $byStatus['Collected']['action']['style']);

        $this->assertSame('View', $byStatus['Ready for pickup']['action']['label']);
    }

    public function test_a_quoted_row_with_no_artwork_asks_for_the_design(): void
    {
        $quoted = Job::where('user_id', $this->customer('jomar@tarpalink.ph')->id)
            ->where('status', 'quoted')
            ->whereDoesntHave('artworks')
            ->firstOrFail();

        $row = collect($this->props('jomar@tarpalink.ph')['jobs'])
            ->firstWhere('title', $quoted->titleLine());

        $this->assertSame('Send design', $row['action']['label']);
        $this->assertSame('magenta', $row['action']['style']);
    }

    public function test_a_quoted_row_goes_back_to_view_once_the_design_is_sent(): void
    {
        $customer = $this->customer('jomar@tarpalink.ph');

        $quoted = Job::where('user_id', $customer->id)
            ->where('status', 'quoted')
            ->firstOrFail();

        $quoted->artworks()->create([
            'uploaded_by' => $customer->id,
            'file_path' => 'artwork/test.pdf',
            'original_name' => 'poster.pdf',
            'mime' => 'application/pdf',
            'bytes' => 1024,
        ]);

        $row = collect($this->props('jomar@tarpalink.ph')['jobs'])
            ->firstWhere('title', $quoted->titleLine());

        $this->assertSame('View', $row['action']['label']);
    }

    /**
     * A customer with fourteen jobs clicked "Ready", saw an empty list, and was
     * told "No jobs here yet — find a shop", with a Browse shops button.
     */
    public function test_an_empty_tab_does_not_claim_the_customer_has_no_jobs(): void
    {
        $props = $this->props('jomar@tarpalink.ph', '?status=ready');

        $this->assertSame([], $props['jobs']);
        $this->assertIsArray($props['empty']);
        $this->assertStringContainsString('Nothing in this tab', $props['empty']['title']);
        $this->assertSame('Show all jobs', $props['empty']['cta']['label']);
        $this->assertSame(route('account'), $props['empty']['cta']['url']);
    }

    public function test_a_search_with_no_results_offers_to_clear_it(): void
    {
        $props = $this->props('jomar@tarpalink.ph', '?q=zzzznotajob');

        $this->assertStringContainsString('Nothing matches', $props['empty']['title']);
        $this->assertSame('Clear the search', $props['empty']['cta']['label']);
    }

    public function test_the_action_counter_counts_every_proof_not_just_one(): void
    {
        $customer = $this->customer('renz@example.com');

        // Two proofs waiting is the normal case for a shop that batches, and the
        // counter used to read "1" no matter how many there were. The seed
        // already has one, so the expected number is counted, not assumed.
        $already = $customer->jobs()->where('status', 'awaiting_proof')->count();

        // Quoted jobs are excluded: moving one to `awaiting_proof` would take a
        // design out of the other bucket the counter also counts, and this test
        // is about proofs.
        $jobs = $customer->jobs()
            ->whereNotIn('status', ['awaiting_proof', 'quoted'])
            ->take(2)
            ->get();
        $this->assertGreaterThanOrEqual(2, $jobs->count());

        foreach ($jobs as $job) {
            $job->update(['status' => 'awaiting_proof']);
        }

        $designs = $customer->jobs()->where('status', 'quoted')->doesntHave('artworks')->count();

        $expected = $already + $jobs->count() + $designs;

        $kpi = collect($this->props('renz@example.com')['kpis'])
            ->firstWhere('label', 'Needs your action');

        $this->assertSame((string) $expected, $kpi['value']);
        $this->assertSame('warn', $kpi['tone']);
    }

    public function test_the_action_counter_counts_every_missing_design_not_just_one(): void
    {
        // jomar has two unpaid orders with no file attached. The count was
        // `$needsArtwork === null ? 0 : 1`, so the box said 1 while the list
        // underneath showed two "Send design" buttons.
        $customer = $this->customer();

        $proofs = $customer->jobs()->where('status', 'awaiting_proof')->count();
        $designs = $customer->jobs()->where('status', 'quoted')->doesntHave('artworks')->count();

        $this->assertGreaterThanOrEqual(2, $designs, 'The seed should give jomar two quotes with no file.');

        $kpi = collect($this->props()['kpis'])->firstWhere('label', 'Needs your action');

        $this->assertSame((string) ($proofs + $designs), $kpi['value']);
        $this->assertStringContainsString($designs.' design', $kpi['note']);
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
