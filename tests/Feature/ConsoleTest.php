<?php

namespace Tests\Feature;

use App\Models\Job;
use App\Models\Shop;
use App\Models\User;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The shop console dashboard, against its props contract.
 *
 * The screen is a Vue component, so the server owns the numbers: the four stats,
 * the due-today rows, the seven bars and the attention list. Each figure here is
 * checked against a plain query over the same rows rather than against the
 * controller's own arithmetic — a test that recomputes the expression the code
 * computes proves nothing.
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

    /**
     * @return array<string, mixed>
     */
    private function props(): array
    {
        return $this->actingAs($this->owner())
            ->get('/console')
            ->assertOk()
            ->viewData('props');
    }

    public function test_the_page_mounts_as_a_vue_component_with_serialisable_props(): void
    {
        $html = $this->actingAs($this->owner())->get('/console')->assertOk()->getContent();

        $this->assertStringContainsString('data-component="shop-dashboard"', $html);

        preg_match('/data-props="([^"]*)"/', $html, $match);
        $this->assertNotEmpty($match, 'The mount host is missing data-props.');

        $decoded = json_decode(html_entity_decode($match[1], ENT_QUOTES), true);
        $this->assertIsArray($decoded, 'data-props is not valid JSON.');
        $this->assertCount(4, $decoded['stats']);
    }

    public function test_the_console_has_its_own_stylesheet_and_no_storefront_shell(): void
    {
        $html = $this->actingAs($this->owner())->get('/console')->assertOk()->getContent();

        // The console is a tool, not a page of the brochure: it must not carry
        // the marketing app bar or the storefront footer, and it must load the
        // console's own stylesheet.
        $this->assertStringContainsString('/build/assets/console-', $html);
        $this->assertStringNotContainsString('class="appbar"', $html);
        $this->assertStringNotContainsString('class="footer"', $html);
    }

    public function test_revenue_today_equals_a_plain_sum_over_paid_payments(): void
    {
        // `payments` joins `jobs`, and both have a `status` column — hence the
        // table prefix, which is the same reason the controller needs one.
        $expected = (int) $this->shop()->payments()
            ->where('payments.status', 'paid')
            ->whereBetween('payments.paid_at', [Carbon::today(), Carbon::today()->endOfDay()])
            ->sum('payments.amount');

        $stat = collect($this->props()['stats'])->firstWhere('label', 'Revenue today');

        $this->assertSame(Money::short($expected), $stat['value']);
    }

    public function test_open_jobs_and_due_today_match_plain_counts(): void
    {
        $open = $this->shop()->jobs()->whereNotIn('status', ['collected', 'cancelled']);
        $openCount = (clone $open)->count();
        $dueToday = (clone $open)
            ->whereBetween('due_at', [Carbon::today(), Carbon::today()->endOfDay()])
            ->count();

        $props = $this->props();

        $this->assertSame((string) $openCount, collect($props['stats'])->firstWhere('label', 'Open jobs')['value']);
        $this->assertSame($dueToday, $props['dueToday']['count']);
        $this->assertCount($dueToday, $props['dueToday']['rows']);
    }

    public function test_the_on_time_rate_counts_collected_jobs_only(): void
    {
        $collected = $this->shop()->jobs()->where('status', 'collected')->whereNotNull('collected_at');
        $total = (clone $collected)->count();
        $onTime = (clone $collected)->whereColumn('collected_at', '<=', 'due_at')->count();

        $stat = collect($this->props()['stats'])->firstWhere('label', 'On-time rate');

        $this->assertSame((int) round($onTime / $total * 100).'%', $stat['value']);
        $this->assertSame($onTime.' of '.$total.' collected on time', $stat['note']);
    }

    public function test_the_average_job_value_excludes_cancelled_work(): void
    {
        $expected = (int) round((float) $this->shop()->jobs()
            ->where('status', '!=', 'cancelled')
            ->where('created_at', '>=', now()->subDays(30))
            ->avg('total'));

        $stat = collect($this->props()['stats'])->firstWhere('label', 'Avg. job value');

        $this->assertSame(Money::short($expected), $stat['value']);
    }

    public function test_only_the_revenue_stat_carries_an_arrow(): void
    {
        // An arrow is a direction of change. "↑ 7 of 7 collected on time" is not
        // a direction, it is a sentence, and the design's fourth card reads wrong
        // with one.
        $arrows = collect($this->props()['stats'])->pluck('arrow', 'label');

        $this->assertContains($arrows['Revenue today'], ['up', 'down']);

        foreach (['Open jobs', 'Avg. job value', 'On-time rate'] as $label) {
            $this->assertNull($arrows[$label], "{$label} should not carry an arrow.");
        }
    }

    public function test_the_week_has_seven_bars_ending_today(): void
    {
        $days = $this->props()['week']['days'];

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

        $this->assertSame(Money::short($expected), $this->props()['week']['total']);
    }

    public function test_needs_attention_lists_the_stuck_jobs_oldest_first(): void
    {
        $rows = $this->props()['attention']['rows'];

        $this->assertNotEmpty($rows);

        $since = array_column($rows, 'since');
        $sorted = $since;
        sort($sorted);

        $this->assertSame($sorted, $since, 'The oldest wait belongs at the top.');

        foreach ($rows as $row) {
            $this->assertArrayHasKey('reference', $row);
            $this->assertArrayHasKey('issue', $row);
            $this->assertArrayHasKey('waiting', $row);
            $this->assertStringContainsString('/shop/jobs/', $row['url']);
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

        $references = collect($this->props()['attention']['rows'])->pluck('reference');

        $this->assertNotContains($job->reference, $references);
    }

    public function test_a_proof_sent_fourteen_hours_ago_is_chased(): void
    {
        $job = Job::where('shop_id', $this->shop()->id)
            ->where('status', 'awaiting_proof')
            ->firstOrFail();

        $job->proofs()->update(['created_at' => now()->subHours(14)]);
        $job->forceFill(['updated_at' => now()->subHours(14)])->save();

        $row = collect($this->props()['attention']['rows'])->firstWhere('reference', $job->reference);

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

        $rows = $this->props()['attention']['rows'];

        $this->assertSame($job->reference, $rows[0]['reference']);
        $this->assertSame('Past its pickup date', $rows[0]['issue']);
    }

    public function test_the_sidebar_marks_exactly_one_item_as_current(): void
    {
        $groups = collect($this->props()['shell']['groups']);
        $active = $groups->flatMap(fn (array $group) => $group['items'])->where('active', true);

        $this->assertCount(1, $active);
        $this->assertSame('Dashboard', $active->first()['label']);
    }

    public function test_the_sidebar_links_to_the_panel_screens_that_are_not_ported_yet(): void
    {
        $items = collect($this->props()['shell']['groups'])
            ->flatMap(fn (array $group) => $group['items'])
            ->pluck('url', 'label');

        // These five are still Filament. The link is the bridge, and it is what
        // keeps the console usable while the port is half done.
        foreach (['Jobs', 'Production board', 'Price matrix', 'Reports', 'Billing'] as $label) {
            $this->assertStringContainsString('/shop/', $items[$label], "{$label} should still point into the panel.");
        }
    }

    public function test_the_jobs_badge_counts_the_open_jobs(): void
    {
        $open = $this->shop()->jobs()->whereNotIn('status', ['collected', 'cancelled'])->count();

        $jobs = collect($this->props()['shell']['groups'])
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

    public function test_an_owner_whose_shop_is_suspended_is_refused(): void
    {
        $this->shop()->forceFill(['status' => 'suspended'])->save();

        $this->actingAs($this->owner())->get('/console')->assertForbidden();
    }

    public function test_a_shop_owner_only_ever_sees_their_own_numbers(): void
    {
        $other = Shop::where('slug', '!=', 'inkwell-press')->firstOrFail();

        $props = $this->props();

        $this->assertSame('Inkwell Press', $props['shell']['brand']);
        $this->assertSame(
            (string) $this->shop()->jobs()->whereNotIn('status', ['collected', 'cancelled'])->count(),
            collect($props['stats'])->firstWhere('label', 'Open jobs')['value'],
        );
        $this->assertNotSame(
            $other->jobs()->whereNotIn('status', ['collected', 'cancelled'])->count(),
            (int) collect($props['stats'])->firstWhere('label', 'Open jobs')['value'],
        );
    }
}
