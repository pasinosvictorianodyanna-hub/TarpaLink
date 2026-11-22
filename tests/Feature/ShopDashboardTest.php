<?php

namespace Tests\Feature;

use App\Filament\Shop\Widgets\DueToday;
use App\Filament\Shop\Widgets\LastSevenDays;
use App\Filament\Shop\Widgets\NeedsAttention;
use App\Filament\Shop\Widgets\ShopStats;
use App\Models\Job;
use App\Models\Payment;
use App\Models\Shop;
use App\Models\User;
use App\Services\JobWorkflow;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Acceptance criteria for issue #17.
 *
 * The dashboard is a set of sums, so the tests compare each card against a
 * plain query over the same rows. A widget that "looks about right" is how a
 * dashboard silently starts lying.
 */
class ShopDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        // Widgets build links through JobResource, which needs a panel the same
        // way a request does. Livewire tests do not go through the panel route,
        // so it has to be set by hand.
        Filament::setCurrentPanel('shop');
    }

    private function owner(): User
    {
        return User::where('email', 'kevin@inkwell.ph')->firstOrFail();
    }

    private function inkwell(): Shop
    {
        return Shop::where('slug', 'inkwell-press')->firstOrFail();
    }

    /** @return array{revenue_today: int, revenue_yesterday: int, open: int, due_today: int, average: int, collected: int, on_time: int} */
    private function figures(): array
    {
        return Livewire::actingAs($this->owner())
            ->test(ShopStats::class)
            ->instance()
            ->figures();
    }

    public function test_the_dashboard_renders_every_card(): void
    {
        $this->actingAs($this->owner())
            ->get('/shop')
            ->assertSuccessful()
            ->assertSee('Revenue today')
            ->assertSee('Open jobs')
            ->assertSee('Avg. job value')
            ->assertSee('On-time rate')
            ->assertSee('Due today')
            ->assertSee('Last 7 days')
            ->assertSee('Needs attention');
    }

    public function test_the_subheading_counts_this_shops_queue(): void
    {
        $open = $this->inkwell()->jobs()
            ->whereNotIn('status', ['collected', 'cancelled'])
            ->count();

        $this->actingAs($this->owner())
            ->get('/shop')
            ->assertSee("{$open} jobs in the queue");
    }

    public function test_revenue_today_equals_a_plain_sum_over_payments(): void
    {
        $expected = (int) Payment::whereIn('job_id', $this->inkwell()->jobs()->select('id'))
            ->where('status', 'paid')
            ->whereBetween('paid_at', [now()->startOfDay(), now()->endOfDay()])
            ->sum('amount');

        $figures = $this->figures();

        $this->assertSame($expected, $figures['revenue_today']);
        $this->assertGreaterThan(0, $figures['revenue_today'], 'The seed should have taken money today.');
    }

    public function test_open_jobs_and_due_today_match_plain_counts(): void
    {
        $figures = $this->figures();

        $open = $this->inkwell()->jobs()
            ->whereNotIn('status', ['collected', 'cancelled'])
            ->count();

        $dueToday = $this->inkwell()->jobs()
            ->whereNotIn('status', ['collected', 'cancelled'])
            ->whereBetween('due_at', [now()->startOfDay(), now()->endOfDay()])
            ->count();

        $this->assertSame($open, $figures['open']);
        $this->assertSame($dueToday, $figures['due_today']);

        // Two jobs are seeded on the counter today; if this drifts, the demo
        // account stops showing the state the design was drawn against.
        $this->assertSame(2, $figures['due_today']);
    }

    public function test_the_on_time_rate_counts_collected_jobs_only(): void
    {
        $figures = $this->figures();

        $collected = $this->inkwell()->jobs()
            ->where('status', 'collected')
            ->whereNotNull('collected_at');

        $this->assertSame((clone $collected)->count(), $figures['collected']);
        $this->assertSame(
            (clone $collected)->whereColumn('collected_at', '<=', 'due_at')->count(),
            $figures['on_time']
        );
        $this->assertLessThanOrEqual($figures['collected'], $figures['on_time']);
    }

    public function test_due_today_only_lists_this_shops_unfinished_jobs(): void
    {
        $jobs = Livewire::actingAs($this->owner())
            ->test(DueToday::class)
            ->instance()
            ->jobs();

        $this->assertCount(2, $jobs);

        foreach ($jobs as $job) {
            $this->assertSame($this->inkwell()->id, $job->shop_id);
            $this->assertTrue($job->due_at->isToday());
            $this->assertNotContains($job->status, ['collected', 'cancelled']);
        }
    }

    public function test_the_seven_day_total_equals_a_plain_sum(): void
    {
        $widget = Livewire::actingAs($this->owner())->test(LastSevenDays::class)->instance();

        $expected = (int) Payment::whereIn('job_id', $this->inkwell()->jobs()->select('id'))
            ->where('status', 'paid')
            ->whereBetween('paid_at', [now()->startOfDay()->subDays(6), now()->endOfDay()])
            ->sum('amount');

        $this->assertSame($expected, $widget->weekTotal());

        $days = $widget->days();

        $this->assertCount(7, $days);
        $this->assertTrue(end($days)['current'], 'The last bar must be today.');
        $this->assertFalse($days[0]['current']);
        $this->assertSame($widget->weekTotal(), array_sum(array_column($days, 'total')));
    }

    public function test_needs_attention_lists_the_stuck_jobs_oldest_first(): void
    {
        $rows = Livewire::actingAs($this->owner())
            ->test(NeedsAttention::class)
            ->instance()
            ->rows();

        $this->assertNotEmpty($rows);

        $since = [];

        foreach ($rows as $row) {
            $this->assertSame($this->inkwell()->id, $row['job']->shop_id);
            $this->assertNotContains($row['job']->status, ['collected', 'cancelled']);
            $this->assertNotSame('', $row['issue']);
            $since[] = $row['since']->getTimestamp();
        }

        $sorted = $since;
        sort($sorted);

        $this->assertSame($sorted, $since, 'Rows are not ordered longest-waiting first.');
    }

    public function test_a_proof_sent_an_hour_ago_is_not_chased(): void
    {
        $job = $this->inkwell()->jobs()->where('status', 'awaiting_proof')->firstOrFail();

        $job->proofs()->delete();
        $job->proofs()->create([
            'version' => 1,
            'file_path' => 'proofs/fresh.pdf',
            'status' => 'sent',
        ]);
        $job->touch();

        $references = array_map(
            fn (array $row) => $row['job']->reference,
            Livewire::actingAs($this->owner())->test(NeedsAttention::class)->instance()->rows()
        );

        $this->assertNotContains($job->reference, $references);
    }

    public function test_collecting_a_job_stamps_the_time_and_counts_as_on_time(): void
    {
        $job = $this->inkwell()->jobs()->where('status', 'ready')->firstOrFail();
        $job->forceFill(['due_at' => now()->addDay()])->save();

        JobWorkflow::transition($job, 'collected', $this->owner());

        $job->refresh();

        $this->assertNotNull($job->collected_at, 'Collecting must stamp collected_at.');
        $this->assertTrue($job->collected_at->lte($job->due_at));
    }

    public function test_a_late_collection_lowers_the_on_time_rate(): void
    {
        $before = $this->figures();

        $job = $this->inkwell()->jobs()->where('status', 'ready')->firstOrFail();
        $job->forceFill(['due_at' => now()->subDay()])->save();

        JobWorkflow::transition($job, 'collected', $this->owner());

        $after = $this->figures();

        $this->assertSame($before['collected'] + 1, $after['collected']);
        $this->assertSame($before['on_time'], $after['on_time'], 'A late collection must not count as on time.');
    }

    public function test_a_job_past_its_pickup_date_is_flagged(): void
    {
        $job = Job::where('shop_id', $this->inkwell()->id)
            ->where('status', 'in_production')
            ->firstOrFail();

        $job->forceFill(['due_at' => now()->subDays(2)])->save();

        $rows = Livewire::actingAs($this->owner())->test(NeedsAttention::class)->instance()->rows();

        $flagged = collect($rows)->firstWhere(fn (array $row) => $row['job']->id === $job->id);

        $this->assertNotNull($flagged);
        $this->assertSame('Past its pickup date', $flagged['issue']);
    }

    /**
     * `quoted` is the state a job is born in at checkout — the order is already
     * placed. Telling the shop the customer "hasn't ordered" describes a step
     * that does not exist, and hides the step that does: the money has not
     * arrived.
     */
    public function test_an_unpaid_quote_is_flagged_as_unpaid(): void
    {
        $job = $this->inkwell()->jobs()->where('status', 'quoted')->firstOrFail();

        $job->payment()->update(['status' => 'pending']);
        $job->forceFill(['created_at' => now()->subHours(30)])->save();

        $flagged = $this->flagged($job);

        $this->assertNotNull($flagged);
        $this->assertSame('Unpaid, payment not confirmed', $flagged['issue']);
        $this->assertStringNotContainsString('ordered', $flagged['issue']);
    }

    /**
     * The mirror case, and the more urgent one: the customer has paid, so the
     * only reason the job has not moved is that the shop has not sent a proof.
     */
    public function test_a_paid_job_with_no_proof_is_flagged_as_the_shops_own_delay(): void
    {
        $job = $this->inkwell()->jobs()->where('status', 'quoted')->firstOrFail();

        $job->payment()->update(['status' => 'paid', 'paid_at' => now()->subHours(10)]);
        $job->forceFill(['created_at' => now()->subHours(10)])->save();

        $flagged = $this->flagged($job);

        $this->assertNotNull($flagged);
        $this->assertSame('Paid, proof not sent yet', $flagged['issue']);
    }

    /**
     * The two thresholds differ on purpose. Six hours of silence on a paid job is
     * the shop dragging its feet; the same six hours on an unpaid one is just a
     * customer who has not got to it yet.
     */
    public function test_an_unpaid_job_is_not_chased_before_the_quote_window(): void
    {
        $job = $this->inkwell()->jobs()->where('status', 'quoted')->firstOrFail();

        $job->payment()->update(['status' => 'pending']);
        $job->forceFill(['created_at' => now()->subHours(10)])->save();

        $this->assertNull($this->flagged($job));
    }

    /** @return array{job: Job, issue: string, waiting: string, since: \Illuminate\Support\Carbon, url: string}|null */
    private function flagged(Job $job): ?array
    {
        $rows = Livewire::actingAs($this->owner())->test(NeedsAttention::class)->instance()->rows();

        return collect($rows)->firstWhere(fn (array $row) => $row['job']->id === $job->id);
    }
}
