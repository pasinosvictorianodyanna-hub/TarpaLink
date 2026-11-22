<?php

namespace Tests\Feature;

use App\Mail\ProofSent;
use App\Models\Job;
use App\Models\JobActivity;
use App\Models\User;
use App\Services\ProofExchange;
use App\Support\ReplyAddress;
use Filament\Facades\Filament;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Replies that come back in.
 *
 * Every notification used to leave with no Reply-To, so a customer who hit
 * "Reply" and typed an answer was writing to a mailbox that did not exist — and
 * the shop's only evidence of that answer was silence. This covers both halves:
 * the address that goes out, and what happens when the answer comes back.
 *
 * The security posture is the part worth testing hardest. The endpoint is
 * unauthenticated by necessity, so the interesting cases are the ones that must
 * *not* be recorded: an unsigned body, a message for a job that does not exist,
 * a stranger claiming to be the customer, and a reply that tries to move the
 * job along.
 */
class InboundMailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        config([
            'services.inbound.address' => 'reply@inbound.tarpalink.test',
            'services.inbound.secret' => 'test-inbound-secret',
        ]);
    }

    private function customer(): User
    {
        return User::where('email', 'jomar@tarpalink.ph')->firstOrFail();
    }

    private function owner(): User
    {
        return User::where('email', 'kevin@inkwell.ph')->firstOrFail();
    }

    private function quotedJob(): Job
    {
        return Job::where('status', 'quoted')
            ->where('user_id', $this->customer()->id)
            ->whereHas('shop', fn ($q) => $q->where('slug', 'inkwell-press'))
            ->firstOrFail();
    }

    /**
     * Post a forwarded message, signed the way a provider would sign it.
     *
     * Raw content rather than `postJson()`: the signature covers the exact
     * bytes, and a test that re-encodes the array is testing its own encoder.
     */
    private function deliver(array $payload, string|false|null $signature = 'auto'): TestResponse
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        $signature = match ($signature) {
            'auto' => 'sha256='.hash_hmac('sha256', $body, (string) config('services.inbound.secret')),
            false, null => null,
            default => $signature,
        };

        $server = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ];

        if ($signature !== null) {
            $server['HTTP_X_INBOUND_SIGNATURE'] = $signature;
        }

        return $this->call('POST', route('webhooks.inbound'), [], [], [], $server, $body);
    }

    private function reply(Job $job, array $overrides = []): array
    {
        return array_merge([
            'recipient' => 'reply+'.$job->reference.'@inbound.tarpalink.test',
            'sender' => $this->customer()->name.' <jomar@tarpalink.ph>',
            'subject' => 'Re: Your proof for '.$job->reference.' is ready to approve',
            'body-plain' => 'The logo is a bit low on the banner, can you lift it?',
            'Message-Id' => '<'.uniqid('msg-', true).'@mail.example>',
        ], $overrides);
    }

    // ---------------------------------------------------------------- outbound

    public function test_a_notification_carries_a_reply_address_that_names_the_job(): void
    {
        Mail::fake();

        $job = $this->quotedJob();
        app(ProofExchange::class)->send($job, 'proofs/v1.pdf');

        Mail::assertSent(ProofSent::class, function (ProofSent $mail) use ($job) {
            $envelope = $mail->envelope();

            return count($envelope->replyTo) === 1
                && $envelope->replyTo[0]->address === 'reply+'.$job->reference.'@inbound.tarpalink.test';
        });
    }

    public function test_no_reply_address_is_set_when_inbound_mail_is_off(): void
    {
        Mail::fake();

        // A Reply-To pointing at a mailbox nobody reads is worse than none: the
        // customer believes they have been heard. Off must mean absent, not
        // present and broken.
        config(['services.inbound.address' => null]);

        $job = $this->quotedJob();
        app(ProofExchange::class)->send($job, 'proofs/v1.pdf');

        Mail::assertSent(ProofSent::class, fn (ProofSent $mail) => $mail->envelope()->replyTo === []);
    }

    public function test_a_reply_address_needs_an_at_sign_to_be_usable(): void
    {
        config(['services.inbound.address' => 'reply']);

        $this->assertNull(ReplyAddress::for('TL-12345'));
        $this->assertFalse(ReplyAddress::enabled());
    }

    // ----------------------------------------------------------------- inbound

    public function test_a_reply_is_filed_on_the_job_it_names(): void
    {
        $job = $this->quotedJob();

        $this->deliver($this->reply($job))
            ->assertStatus(202)
            ->assertJson(['received' => true, 'matched' => true]);

        $activity = $job->activities()->where('source', JobActivity::EMAIL)->sole();

        $this->assertSame('The logo is a bit low on the banner, can you lift it?', $activity->body);
        // The display name in the header is ignored in favour of the account's
        // own name: the header is whatever the sender typed.
        $this->assertSame('Reply by email from '.$this->customer()->name, $activity->note);
        $this->assertSame(JobActivity::NOTE, $activity->kind);
        $this->assertNull($activity->to_status);
    }

    public function test_a_reply_is_matched_by_the_subject_when_the_tag_is_gone(): void
    {
        $job = $this->quotedJob();

        // A forward, or a client that rewrote the recipient. The reference is
        // still in the subject because every subject line we send carries it.
        $this->deliver($this->reply($job, [
            'recipient' => 'orders@inbound.tarpalink.test',
        ]))->assertJson(['matched' => true]);

        $this->assertSame(1, $job->activities()->where('source', JobActivity::EMAIL)->count());
    }

    public function test_a_message_that_names_no_job_is_accepted_and_dropped(): void
    {
        $this->deliver([
            'recipient' => 'orders@inbound.tarpalink.test',
            'sender' => 'someone@example.com',
            'subject' => 'Hello?',
            'body-plain' => 'Is anyone there?',
            'Message-Id' => '<orphan@mail.example>',
        ])->assertStatus(202)->assertJson(['received' => true, 'matched' => false]);

        // A 4xx here would make every provider retry a message that can never
        // match, forever.
        $this->assertSame(0, JobActivity::where('source', JobActivity::EMAIL)->count());
    }

    public function test_a_message_for_a_job_that_does_not_exist_is_dropped(): void
    {
        $this->deliver([
            'recipient' => 'reply+TL-00000@inbound.tarpalink.test',
            'sender' => 'someone@example.com',
            'subject' => 'Re: Your proof for TL-00000 is ready to approve',
            'body-plain' => 'Nobody home.',
        ])->assertStatus(202)->assertJson(['matched' => false]);

        $this->assertSame(0, JobActivity::where('source', JobActivity::EMAIL)->count());
    }

    public function test_a_reply_never_moves_the_job(): void
    {
        $job = $this->quotedJob();

        // "Approved, go ahead" in an email is a note the shop reads. Approval
        // itself stays behind the signed-in link, because anyone who learns a
        // reference number can send an email claiming to be anyone.
        $this->deliver($this->reply($job, [
            'body-plain' => 'Approved, go ahead and print it.',
        ]))->assertStatus(202);

        $this->assertSame('quoted', $job->fresh()->status);
        $this->assertNull($job->fresh()->activities()->whereNotNull('to_status')->first());
    }

    // ----------------------------------------------------------------- signing

    public function test_an_unsigned_request_is_rejected(): void
    {
        $job = $this->quotedJob();

        $this->deliver($this->reply($job), false)->assertStatus(401);

        $this->assertSame(0, JobActivity::where('source', JobActivity::EMAIL)->count());
    }

    public function test_a_wrongly_signed_request_is_rejected(): void
    {
        $job = $this->quotedJob();

        $this->deliver($this->reply($job), 'sha256='.str_repeat('0', 64))->assertStatus(401);

        $this->assertSame(0, JobActivity::where('source', JobActivity::EMAIL)->count());
    }

    public function test_inbound_mail_is_refused_when_no_secret_is_configured(): void
    {
        config(['services.inbound.secret' => null]);

        $job = $this->quotedJob();

        // An endpoint that appends to a job's history without a secret is an
        // open invitation to fill a shop's records with anything.
        $this->deliver($this->reply($job), 'sha256='.hash_hmac('sha256', 'anything', 'anything'))
            ->assertStatus(401);

        $this->assertSame(0, JobActivity::where('source', JobActivity::EMAIL)->count());
    }

    public function test_a_bare_hex_signature_is_accepted(): void
    {
        $job = $this->quotedJob();
        $payload = $this->reply($job);
        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        // A hand-rolled relay sends the digest without the `sha256=` prefix.
        $this->call('POST', route('webhooks.inbound'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_INBOUND_SIGNATURE' => hash_hmac('sha256', $body, (string) config('services.inbound.secret')),
        ], $body)->assertStatus(202)->assertJson(['matched' => true]);
    }

    public function test_a_malformed_body_is_rejected(): void
    {
        $body = 'not json at all';
        $signature = 'sha256='.hash_hmac('sha256', $body, (string) config('services.inbound.secret'));

        $this->call('POST', route('webhooks.inbound'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_INBOUND_SIGNATURE' => $signature,
        ], $body)->assertStatus(422);
    }

    public function test_both_webhooks_are_exempt_from_csrf_by_path(): void
    {
        // This used to be a per-route `withoutMiddleware(VerifyCsrfToken::class)`,
        // which silently excluded nothing: the `web` group registers
        // `ValidateCsrfToken`, a different class name, so every live webhook
        // would have been answered with a 419. No test caught it because the
        // middleware short-circuits when the application is running tests.
        $excluded = app(ValidateCsrfToken::class)->getExcludedPaths();

        $this->assertContains('webhooks/*', $excluded);
    }

    // ------------------------------------------------------------------- retry

    public function test_a_provider_retry_does_not_file_the_same_reply_twice(): void
    {
        $job = $this->quotedJob();
        $payload = $this->reply($job, ['Message-Id' => '<fixed-message-id@mail.example>']);

        $this->deliver($payload)->assertJson(['matched' => true]);
        $this->deliver($payload)->assertJson(['matched' => false]);

        // Two identical replies in a customer's history is a bug that reads like
        // a person.
        $this->assertSame(1, $job->activities()->where('source', JobActivity::EMAIL)->count());
    }

    public function test_a_retry_without_a_message_id_is_still_recognised(): void
    {
        $job = $this->quotedJob();
        $payload = $this->reply($job);
        unset($payload['Message-Id']);

        $this->deliver($payload)->assertJson(['matched' => true]);
        $this->deliver($payload)->assertJson(['matched' => false]);

        $this->assertSame(1, $job->activities()->where('source', JobActivity::EMAIL)->count());
    }

    // ------------------------------------------------------------------ sender

    public function test_the_sender_is_attributed_when_it_is_the_customer(): void
    {
        $job = $this->quotedJob();

        $this->deliver($this->reply($job))->assertStatus(202);

        $activity = $job->activities()->where('source', JobActivity::EMAIL)->sole();

        $this->assertSame($this->customer()->id, $activity->user_id);
        $this->assertSame($this->customer()->name, $activity->user->name);
    }

    public function test_the_sender_is_attributed_case_insensitively(): void
    {
        $job = $this->quotedJob();

        // Mail headers are not normalised; the same person writes Maria@ and
        // maria@ within a week.
        $this->deliver($this->reply($job, [
            'sender' => 'JOMAR@TARPALINK.PH',
        ]))->assertStatus(202);

        $this->assertSame(
            $this->customer()->id,
            $job->activities()->where('source', JobActivity::EMAIL)->sole()->user_id,
        );
    }

    public function test_a_shop_staff_reply_is_attributed_to_them(): void
    {
        $job = $this->quotedJob();

        $this->deliver($this->reply($job, [
            'sender' => 'Kevin Reyes <kevin@inkwell.ph>',
            'body-plain' => 'Lifting the logo now, new proof in ten minutes.',
        ]))->assertStatus(202);

        $this->assertSame(
            $this->owner()->id,
            $job->activities()->where('source', JobActivity::EMAIL)->sole()->user_id,
        );
    }

    public function test_a_stranger_is_recorded_without_a_name_attached(): void
    {
        $job = $this->quotedJob();

        // The words still land — the shop should see what was said — but they
        // are not attributed to a person the job has nothing to do with.
        $this->deliver($this->reply($job, [
            'sender' => 'nobody@example.com',
            'body-plain' => 'Cancel this job please.',
        ]))->assertStatus(202);

        $activity = $job->activities()->where('source', JobActivity::EMAIL)->sole();

        $this->assertNull($activity->user_id);
        $this->assertSame('Reply by email from nobody@example.com', $activity->note);
    }

    // ------------------------------------------------------------------- body

    public function test_the_html_part_is_reduced_to_readable_text(): void
    {
        $job = $this->quotedJob();

        $this->deliver($this->reply($job, [
            'body-plain' => null,
            'body-html' => '<html><head><style>p{color:red}</style></head><body>'
                .'<p>Hi &amp; hello,</p><p>The banner is <b>upside down</b>.</p>'
                .'<p>Thanks,<br>Jomar</p></body></html>',
        ]))->assertStatus(202);

        $body = $job->activities()->where('source', JobActivity::EMAIL)->sole()->body;

        $this->assertStringNotContainsString('<', $body);
        $this->assertStringNotContainsString('color:red', $body);
        $this->assertStringContainsString('Hi & hello,', $body);
        $this->assertStringContainsString('The banner is upside down.', $body);
        $this->assertStringContainsString("Thanks,\nJomar", $body);
    }

    public function test_an_oversized_body_is_cut_down(): void
    {
        config(['services.inbound.max_body' => 120]);

        $job = $this->quotedJob();

        $this->deliver($this->reply($job, [
            'body-plain' => str_repeat('a quoted thread goes on. ', 400),
        ]))->assertStatus(202);

        $body = $job->activities()->where('source', JobActivity::EMAIL)->sole()->body;

        // An inbound endpoint that will store an unbounded body is a way to fill
        // a disk from the outside.
        $this->assertLessThanOrEqual(121, mb_strlen($body));
        $this->assertStringEndsWith('…', $body);
    }

    public function test_a_provider_shaped_payload_is_understood(): void
    {
        $job = $this->quotedJob();

        // Postmark's spelling of the same six fields.
        $this->deliver([
            'To' => 'TarpaLink <reply+'.$job->reference.'@inbound.tarpalink.test>',
            'From' => 'Jomar Dela Cruz <jomar@tarpalink.ph>',
            'Subject' => 'Re: Your proof for '.$job->reference,
            'TextBody' => 'Second version is perfect.',
            'MessageID' => '<postmark-shaped@mail.example>',
        ])->assertStatus(202)->assertJson(['matched' => true]);

        $this->assertSame(
            'Second version is perfect.',
            $job->activities()->where('source', JobActivity::EMAIL)->sole()->body,
        );
    }

    // ---------------------------------------------------------------- history

    public function test_the_job_history_shows_the_reply(): void
    {
        $job = $this->quotedJob();

        $this->deliver($this->reply($job, [
            'body-plain' => "The logo is low.\nCan you lift it by an inch?",
        ]))->assertStatus(202);

        // The console's job page is the shop's view of the history now; the
        // panel's job screen is gone with the panel.
        $this->actingAs($this->owner())
            ->get('/console/jobs/'.$job->getRouteKey())
            ->assertOk()
            ->assertSee('Reply by email from '.$this->customer()->name)
            ->assertSee('Can you lift it by an inch?');
    }

    public function test_the_history_splits_a_reply_into_readable_lines(): void
    {
        $job = $this->quotedJob();

        $this->deliver($this->reply($job, [
            'body-plain' => "First line.\n\nSecond paragraph.",
        ]))->assertStatus(202);

        $lines = $job->activities()->where('source', JobActivity::EMAIL)->sole()->bodyLines();

        // The blank line between paragraphs is dropped rather than rendered as
        // an empty entry, and the quote marks bracket the whole reply.
        $this->assertSame(['“First line.', 'Second paragraph.”'], $lines);
    }

    public function test_a_long_reply_is_truncated_in_the_history(): void
    {
        $job = $this->quotedJob();

        $this->deliver($this->reply($job, [
            'body-plain' => implode("\n", array_fill(0, 40, 'A quoted line.')),
        ]))->assertStatus(202);

        $lines = $job->activities()->where('source', JobActivity::EMAIL)->sole()->bodyLines();

        $this->assertCount(12, $lines);
        $this->assertStringEndsWith('…', end($lines));
    }

    public function test_a_typed_note_still_renders_as_one_line(): void
    {
        $job = $this->quotedJob();

        $note = $job->activities()->create([
            'kind' => JobActivity::NOTE,
            'note' => 'Shop nudged the customer',
        ]);

        $this->assertSame([], $note->bodyLines());
        $this->assertStringContainsString('Shop nudged the customer', $note->headline());
        $this->assertFalse($note->isEmail());
    }

    protected function tearDown(): void
    {
        // A proof written by another test would leak into the next run.
        Storage::disk('proofs')->deleteDirectory('proofs');

        parent::tearDown();
    }
}
