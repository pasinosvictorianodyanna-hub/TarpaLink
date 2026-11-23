<?php

namespace Tests\Feature;

use App\Models\Job;
use App\Models\Shop;
use App\Models\User;
use App\Services\JobWorkflow;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The shop console dashboard.
 *
 * The screen is server-rendered now, so the server still owns the numbers — the
 * four stats, the due-today rows, the seven bars and the attention list. Each
 * figure is checked against a plain query over the same rows rather than against
 * the controller's own arithmetic: a test that recomputes the expression the
 * code computes proves nothing.
 */
class ConsoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function owner(): User
    {
        return User::where('email', 'kevin@inkwell.ph')->firstOrFail();
    }

    private function shop(): Shop
    {
        return Shop::where('slug', 'inkwell-press')->firstOrFail();
    }

    /** The dashboard as the owner sees it. */
    private function dashboard(): TestResponse
    {
        return $this->actingAs($this->owner())->get('/console')->assertOk();
    }

    public function test_the_dashboard_is_rendered_by_the_server_with_the_shell(): void
    {
        $html = $this->dashboard()->getContent();

        // The shell used to be a Vue component, which meant the console could
        // only ever have one screen. It is a layout now, and the sidebar is in
        // the HTML before any JavaScript runs.
        $this->assertStringContainsString('aria-label="Shop console navigation"', $html);
        $this->assertStringContainsString('Inkwell Press', $html);
        $this->assertStringNotContainsString('data-component="shop-dashboard"', $html);
    }

    public function test_the_console_has_its_own_stylesheet_and_no_storefront_shell(): void
    {
        $html = $this->dashboard()->getContent();

        // The console is a tool, not a page of the brochure: it must not carry
        // the marketing app bar or the storefront footer, and it must load the
        // console's own stylesheet.
        //
        // The stylesheet is matched by name, not by build path. It arrives as
        // `/build/assets/console-*.css` normally and as `/resources/css/console.css`
        // when Vite is serving — and asserting the first form made this test
        // depend on whether the developer happened to have `npm run dev` open.
        // The question worth asking is "does the console load console.css", and
        // that has one answer in both modes.
        $this->assertMatchesRegularExpression(
            '#(/build/assets/console-[^"]+\.css|/resources/css/console\.css)#',
            $html,
            'The console page does not reference console.css.',
        );

        $this->assertStringNotContainsString('class="appbar"', $html);
        $this->assertStringNotContainsString('class="footer"', $html);
    }

    public function test_the_console_ships_no_storefront_javascript(): void
    {
        $html = $this->dashboard()->getContent();

        // The dashboard has no island, so the page must not pull the Vue bundle
        // in "just in case" — that is a runtime a drawer does not need. Both
        // forms are checked: the built bundle and the dev-server entry point,
        // because asserting only the first passes vacuously whenever Vite is up.
        $this->assertStringNotContainsString('/build/assets/app-', $html);
        $this->assertStringNotContainsString('resources/js/app.js', $html);
    }

    public function test_revenue_today_equals_a_plain_sum_over_paid_payments(): void
    {
        // `payments` joins `jobs`, and both have a `status` column — hence the
        // table prefix, which is the same reason the controller needs one.
        $expected = (int) $this->shop()->payments()
            ->where('payments.status', 'paid')
            ->whereBetween('payments.paid_at', [Carbon::today(), Carbon::today()->endOfDay()])
            ->sum('payments.amount');

        $stat = collect($this->dashboard()->viewData('stats'))->firstWhere('label', 'Revenue today');

        $this->assertSame(Money::short($expected), $stat['value']);
    }

    public function test_open_jobs_and_due_today_match_plain_counts(): void
    {
        $open = $this->shop()->jobs()->whereNotIn('status', ['collected', 'cancelled']);
        $openCount = (clone $open)->count();
        $dueToday = (clone $open)
            ->whereBetween('due_at', [Carbon::today(), Carbon::today()->endOfDay()])
            ->count();

        $response = $this->dashboard();

        $this->assertSame((string) $openCount, collect($response->viewData('stats'))->firstWhere('label', 'Open jobs')['value']);
        $this->assertSame($dueToday, $response->viewData('dueToday')['count']);
        $this->assertCount($dueToday, $response->viewData('dueToday')['rows']);
    }

    public function test_the_on_time_rate_counts_collected_jobs_only(): void
    {
        $collected = $this->shop()->jobs()->where('status', 'collected')->whereNotNull('collected_at');
        $total = (clone $collected)->count();
        $onTime = (clone $collected)->whereColumn('collected_at', '<=', 'due_at')->count();

        $stat = collect($this->dashboard()->viewData('stats'))->firstWhere('label', 'On-time rate');

        $this->assertSame((int) round($onTime / $total * 100).'%', $stat['value']);
        $this->assertSame($onTime.' of '.$total.' collected on time', $stat['note']);
    }

    public function test_the_average_job_value_excludes_cancelled_work(): void
    {
        $expected = (int) round((float) $this->shop()->jobs()
            ->where('status', '!=', 'cancelled')
            ->where('created_at', '>=', now()->subDays(30))
            ->avg('total'));

        $stat = collect($this->dashboard()->viewData('stats'))->firstWhere('label', 'Avg. job value');

        $this->assertSame(Money::short($expected), $stat['value']);
    }

    public function test_only_the_revenue_stat_carries_an_arrow(): void
    {
        // An arrow is a direction of change. "↑ 7 of 7 collected on time" is not
        // a direction, it is a sentence, and the design's fourth card reads wrong
        // with one.
        $arrows = collect($this->dashboard()->viewData('stats'))->pluck('arrow', 'label');

        $this->assertContains($arrows['Revenue today'], ['up', 'down']);

        foreach (['Open jobs', 'Avg. job value', 'On-time rate'] as $label) {
            $this->assertNull($arrows[$label], "{$label} should not carry an arrow.");
        }
    }

    public function test_the_week_has_seven_bars_ending_today(): void
    {
        $days = $this->dashboard()->viewData('week')['days'];

        $this->assertCount(7, $days);
        $this->assertTrue($days[6]['current'], 'The last bar is today.');
        $this->assertCount(1, collect($days)->where('current', true));

        foreach ($days as $day) {
            // A zero day still needs a baseline, or the chart looks broken.
            $this->assertGreaterThanOrEqual(2, $day['pct']);
            $this->assertLessThanOrEqual(100, $day['pct']);
        }
    }

    public function test_the_week_total_equals_the_sum_of_seven_days_of_payments(): void
    {
        $expected = (int) $this->shop()->payments()
            ->where('payments.status', 'paid')
            ->whereBetween('payments.paid_at', [Carbon::today()->subDays(6)->startOfDay(), Carbon::today()->endOfDay()])
            ->sum('payments.amount');

        $this->assertSame(Money::short($expected), $this->dashboard()->viewData('week')['total']);
    }

    public function test_needs_attention_lists_the_stuck_jobs_oldest_first(): void
    {
        $rows = $this->dashboard()->viewData('attention')['rows'];

        $this->assertNotEmpty($rows);

        $since = array_column($rows, 'since');
        $sorted = $since;
        sort($sorted);

        $this->assertSame($sorted, $since, 'The oldest wait belongs at the top.');

        foreach ($rows as $row) {
            $this->assertArrayHasKey('reference', $row);
            $this->assertArrayHasKey('issue', $row);
            $this->assertArrayHasKey('waiting', $row);
            $this->assertStringContainsString('/console/jobs/', $row['url']);
        }
    }

    public function test_a_proof_sent_an_hour_ago_is_not_chased(): void
    {
        // Chasing a customer two hours after the proof went out trains the owner
        // to ignore the list, which makes the list worthless.
        $job = Job::where('shop_id', $this->shop()->id)
            ->where('status', 'awaiting_proof')
            ->firstOrFail();

        $job->proofs()->update(['created_at' => now()->subHour()]);
        $job->forceFill(['updated_at' => now()->subHour()])->save();

        $references = collect($this->dashboard()->viewData('attention')['rows'])->pluck('reference');

        $this->assertNotContains($job->reference, $references);
    }

    public function test_a_proof_sent_fourteen_hours_ago_is_chased(): void
    {
        $job = Job::where('shop_id', $this->shop()->id)
            ->where('status', 'awaiting_proof')
            ->firstOrFail();

        $job->proofs()->update(['created_at' => now()->subHours(14)]);
        $job->forceFill(['updated_at' => now()->subHours(14)])->save();

        $row = collect($this->dashboard()->viewData('attention')['rows'])->firstWhere('reference', $job->reference);

        $this->assertNotNull($row);
        $this->assertStringContainsString("customer hasn't approved", $row['issue']);
        $this->assertSame('14 hrs', $row['waiting']);
    }

    public function test_a_job_past_its_pickup_date_outranks_everything_else(): void
    {
        $job = Job::where('shop_id', $this->shop()->id)
            ->where('status', 'in_production')
            ->firstOrFail();

        $job->forceFill(['due_at' => now()->subDays(3)])->save();

        $rows = $this->dashboard()->viewData('attention')['rows'];

        $this->assertSame($job->reference, $rows[0]['reference']);
        $this->assertSame('Past its pickup date', $rows[0]['issue']);
    }

    /**
     * `quoted` is the state a job is born in at checkout — the order is already
     * placed. Telling the shop the customer "hasn't ordered" describes a step
     * that does not exist, and hides the step that does: the money has not
     * arrived.
     *
     * This is a regression guard rather than a new rule. The panel's copy was
     * fixed for exactly this, and the port to the console reintroduced the old
     * wording because these tests were not carried across with it.
     */
    public function test_an_unpaid_quote_is_flagged_as_unpaid(): void
    {
        $job = $this->quotedJob();
        $job->payment()->update(['status' => 'pending']);
        $job->forceFill(['created_at' => now()->subHours(30)])->save();

        $flagged = $this->flagged($job);

        $this->assertNotNull($flagged);
        $this->assertSame('Unpaid, payment not confirmed', $flagged['issue']);
        $this->assertStringNotContainsString('ordered', $flagged['issue']);
    }

    /**
     * The mirror case, and the more urgent one: the customer has paid and sent
     * the design, so the only reason the job has not moved is that the shop has
     * not made the proof.
     */
    public function test_a_paid_job_with_a_design_and_no_proof_is_flagged_as_the_shops_own_delay(): void
    {
        $job = $this->quotedJob();
        $job->payment()->update(['status' => 'paid', 'paid_at' => now()->subHours(10)]);
        $job->forceFill(['created_at' => now()->subHours(10)])->save();

        // The design is what makes this the shop's delay. Without it the shop is
        // blocked on a file only the customer can send, which is the next test.
        $job->artworks()->create([
            'uploaded_by' => $job->user_id,
            'file_path' => 'artwork/poster.pdf',
            'original_name' => 'poster.pdf',
            'mime' => 'application/pdf',
            'bytes' => 40_000,
        ]);

        $flagged = $this->flagged($job);

        $this->assertNotNull($flagged);
        $this->assertSame('Paid, proof not sent yet', $flagged['issue']);
    }

    /**
     * A paid job with no design is not the shop dragging its feet — it is the
     * shop unable to start. The old copy called both cases "proof not sent yet",
     * which told the owner to hurry up on a job waiting for somebody else.
     */
    public function test_a_paid_job_with_no_design_waits_on_the_customer(): void
    {
        $job = $this->quotedJob();
        $job->payment()->update(['status' => 'paid', 'paid_at' => now()->subHours(10)]);
        $job->forceFill(['created_at' => now()->subHours(10)])->save();

        $this->assertCount(0, $job->artworks);

        $flagged = $this->flagged($job);

        $this->assertNotNull($flagged);
        $this->assertSame("Paid, waiting for the customer's design", $flagged['issue']);
        $this->assertStringNotContainsString('proof not sent', $flagged['issue']);
    }

    /**
     * The two thresholds differ on purpose. Six hours of silence on a paid job is
     * the shop dragging its feet; the same six hours on an unpaid one is just a
     * customer who has not got to it yet.
     */
    public function test_an_unpaid_job_is_not_chased_before_the_quote_window(): void
    {
        $job = $this->quotedJob();
        $job->payment()->update(['status' => 'pending']);
        $job->forceFill(['created_at' => now()->subHours(10)])->save();

        $this->assertNull($this->flagged($job));
    }

    /**
     * "Due today" is a list of work, so it has to be this shop's work and it has
     * to be today — a count alone would pass while the rows belonged to somebody
     * else.
     */
    public function test_due_today_lists_this_shops_unfinished_jobs_in_time_order(): void
    {
        $expected = $this->shop()->jobs()
            ->whereNotIn('status', ['collected', 'cancelled'])
            ->whereBetween('due_at', [Carbon::today(), Carbon::today()->endOfDay()])
            ->orderBy('due_at')
            ->limit(6)
            ->pluck('reference')
            ->all();

        $rows = $this->dashboard()->viewData('dueToday')['rows'];

        $this->assertSame($expected, array_column($rows, 'reference'));
    }

    public function test_a_late_collection_lowers_the_on_time_rate(): void
    {
        $before = $this->onTime();

        $job = $this->shop()->jobs()->where('status', 'ready')->firstOrFail();
        $job->forceFill(['due_at' => now()->subDay()])->save();

        JobWorkflow::transition($job, 'collected', $this->owner());

        $after = $this->onTime();

        $this->assertSame($before[1] + 1, $after[1], 'The collected count should rise by one.');
        $this->assertSame($before[0], $after[0], 'A late collection must not count as on time.');
    }

    public function test_collecting_a_job_stamps_the_time_and_counts_as_on_time(): void
    {
        $job = $this->shop()->jobs()->where('status', 'ready')->firstOrFail();
        $job->forceFill(['due_at' => now()->addDay()])->save();

        JobWorkflow::transition($job, 'collected', $this->owner());

        $job->refresh();

        $this->assertNotNull($job->collected_at, 'Collecting must stamp collected_at.');
        $this->assertTrue($job->collected_at->lte($job->due_at));
    }

    // ------------------------------------------------------------- helpers

    /** A job in `quoted` with its payment loaded, so isPaid() reads a row. */
    private function quotedJob(): Job
    {
        return Job::where('shop_id', $this->shop()->id)
            ->where('status', 'quoted')
            ->firstOrFail()
            ->load('payment');
    }

    /** @return array{issue: string, waiting: string}|null */
    private function flagged(Job $job): ?array
    {
        return collect($this->dashboard()->viewData('attention')['rows'])
            ->firstWhere('reference', $job->reference);
    }

    /** [on time, collected] straight off the stat's own sentence. */
    private function onTime(): array
    {
        $note = collect($this->dashboard()->viewData('stats'))->firstWhere('label', 'On-time rate')['note'];

        preg_match('/(\d+) of (\d+) collected on time/', $note, $m);

        return [(int) $m[1], (int) $m[2]];
    }

    public function test_the_sidebar_marks_exactly_one_item_as_current(): void
    {
        $groups = collect($this->dashboard()->viewData('shell')['groups']);
        $active = $groups->flatMap(fn (array $group) => $group['items'])->where('active', true);

        $this->assertCount(1, $active);
        $this->assertSame('Dashboard', $active->first()['label']);
    }

    public function test_every_sidebar_link_stays_inside_the_console(): void
    {
        $items = collect($this->dashboard()->viewData('shell')['groups'])
            ->flatMap(fn (array $group) => $group['items'])
            ->pluck('url', 'label');

        // The whole point of the port: no nav item may leave for the Filament
        // panel, because that is the click that used to change the shell.
        foreach ($items as $label => $url) {
            $this->assertStringContainsString('/console', $url, "{$label} still points into the panel.");
        }
    }

    public function test_the_jobs_badge_counts_the_open_jobs(): void
    {
        $open = $this->shop()->jobs()->whereNotIn('status', ['collected', 'cancelled'])->count();

        $jobs = collect($this->dashboard()->viewData('shell')['groups'])
            ->flatMap(fn (array $group) => $group['items'])
            ->firstWhere('label', 'Jobs');

        $this->assertSame($open, $jobs['badge']);
    }

    public function test_a_customer_is_refused(): void
    {
        $this->actingAs(User::where('email', 'jomar@tarpalink.ph')->firstOrFail())
            ->get('/console')
            ->assertForbidden();
    }

    public function test_a_guest_is_sent_to_the_login(): void
    {
        $this->get('/console')->assertRedirect('/login');
    }

    public function test_an_owner_whose_shop_is_suspended_waits_instead_of_entering(): void
    {
        $this->shop()->forceFill(['status' => 'suspended'])->save();

        $this->actingAs($this->owner())->get('/console')->assertRedirect(route('console.waiting'));
    }

    public function test_a_shop_owner_only_ever_sees_their_own_numbers(): void
    {
        $other = Shop::where('slug', '!=', 'inkwell-press')->firstOrFail();

        $response = $this->dashboard();

        $this->assertSame('Inkwell Press', $response->viewData('shell')['brand']);
        $this->assertSame(
            (string) $this->shop()->jobs()->whereNotIn('status', ['collected', 'cancelled'])->count(),
            collect($response->viewData('stats'))->firstWhere('label', 'Open jobs')['value'],
        );
        $this->assertNotSame(
            $other->jobs()->whereNotIn('status', ['collected', 'cancelled'])->count(),
            (int) collect($response->viewData('stats'))->firstWhere('label', 'Open jobs')['value'],
        );
    }
}
