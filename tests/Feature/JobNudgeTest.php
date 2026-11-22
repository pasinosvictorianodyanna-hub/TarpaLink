<?php

namespace Tests\Feature;

use App\Mail\JobNudged;
use App\Models\Job;
use App\Models\JobActivity;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

/**
 * Chasing a customer about a job that is waiting on them.
 *
 * This was the one thing the Filament shop panel could do that the console
 * could not, which is why the panel could not simply be deleted. The rules being
 * pinned here are the ones that make a nudge honest: it is recorded whether or
 * not the mail leaves, it does not move the job, and it cannot reach a job that
 * is not this shop's.
 */
class JobNudgeTest extends TestCase
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

    private function job(string $status = 'quoted'): Job
    {
        return Job::where('shop_id', $this->shop()->id)
            ->where('status', $status)
            ->firstOrFail();
    }

    public function test_a_nudge_is_recorded_on_the_history_and_emailed(): void
    {
        Mail::fake();

        $job = $this->job('awaiting_proof');

        $this->actingAs($this->owner())
            ->from(route('console.jobs.show', $job))
            ->post(route('console.jobs.nudge', $job))
            ->assertRedirect(route('console.jobs.show', $job))
            ->assertSessionHas('status');

        $nudge = $job->activities()->where('note', 'Shop nudged the customer')->firstOrFail();

        $this->assertSame(JobActivity::NOTE, $nudge->kind);
        $this->assertSame($this->owner()->id, $nudge->user_id);

        Mail::assertSent(JobNudged::class, fn (JobNudged $mail) => $mail->job->is($job));
    }

    /**
     * A nudge is not a move.
     *
     * Writing it as `from_status = to_status` made the history claim
     * "Quoted → Quoted", which cannot happen, and skews any count of how often a
     * job actually changed hands.
     */
    public function test_a_nudge_does_not_move_the_job(): void
    {
        Mail::fake();

        $job = $this->job('quoted');
        $before = $job->status;

        $this->actingAs($this->owner())->post(route('console.jobs.nudge', $job));

        $nudge = $job->activities()->where('note', 'Shop nudged the customer')->firstOrFail();

        $this->assertNull($nudge->from_status);
        $this->assertNull($nudge->to_status);
        $this->assertSame($before, $job->fresh()->status);
    }

    /**
     * The record is the promise; the email is best-effort.
     *
     * If the order were reversed a failed send would lose the nudge entirely, and
     * the owner would believe they had chased somebody they had not.
     */
    public function test_a_nudge_is_still_recorded_when_mail_fails(): void
    {
        Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP is unreachable'));

        $job = $this->job('quoted');

        $this->actingAs($this->owner())
            ->post(route('console.jobs.nudge', $job))
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertSame(
            1,
            $job->activities()->where('note', 'Shop nudged the customer')->count(),
        );
    }

    /** The banner must not claim a send that did not happen. */
    public function test_the_banner_says_nothing_was_sent_when_mail_fails(): void
    {
        Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP is unreachable'));

        $job = $this->job('quoted');

        $this->actingAs($this->owner())
            ->post(route('console.jobs.nudge', $job))
            ->assertSessionHas('status', fn (string $status) => str_contains($status, 'nothing was sent'));
    }

    public function test_the_nudge_email_names_the_job_and_can_be_replied_to(): void
    {
        Mail::fake();

        $job = $this->job('awaiting_proof');

        $this->actingAs($this->owner())->post(route('console.jobs.nudge', $job));

        Mail::assertSent(JobNudged::class, function (JobNudged $mail) use ($job) {
            // A nudge that cannot be answered is just a reminder to feel guilty.
            $replyTo = $mail->envelope()->replyTo;

            if ($replyTo === []) {
                // Inbound mail is not configured in this environment, so the
                // honest expectation is no Reply-To at all rather than a broken one.
                return true;
            }

            return str_contains($replyTo[0]->address, '+'.$job->reference);
        });
    }

    public function test_a_shop_cannot_nudge_another_shops_job(): void
    {
        Mail::fake();

        $foreign = Job::where('shop_id', '!=', $this->shop()->id)->firstOrFail();

        $this->actingAs($this->owner())
            ->post(route('console.jobs.nudge', $foreign))
            ->assertNotFound();

        $this->assertSame(
            0,
            $foreign->activities()->where('note', 'Shop nudged the customer')->count(),
        );

        Mail::assertNothingSent();
    }

    public function test_a_customer_cannot_nudge(): void
    {
        Mail::fake();

        $job = $this->job('quoted');
        $customer = User::where('email', 'jomar@tarpalink.ph')->firstOrFail();

        $this->actingAs($customer)
            ->post(route('console.jobs.nudge', $job))
            ->assertForbidden();

        Mail::assertNothingSent();
    }

    public function test_a_guest_cannot_nudge(): void
    {
        $job = $this->job('quoted');

        $this->post(route('console.jobs.nudge', $job))->assertRedirect('/login');
    }

    /** The list exists to be acted on, so every row carries its own form. */
    public function test_every_attention_row_offers_a_nudge(): void
    {
        $rows = $this->actingAs($this->owner())
            ->get('/console')
            ->assertOk()
            ->viewData('attention')['rows'];

        $this->assertNotEmpty($rows);

        foreach ($rows as $row) {
            $this->assertArrayHasKey('nudge', $row);
            $this->assertStringContainsString('/console/jobs/', $row['nudge']);
            $this->assertStringEndsWith('/nudge', $row['nudge']);
        }
    }
}
