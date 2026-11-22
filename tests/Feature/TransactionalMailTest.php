<?php

namespace Tests\Feature;

use App\Jobs\ProcessPayMongoWebhook;
use App\Mail\PaymentReceipt;
use App\Mail\ProofDecided;
use App\Mail\ProofSent;
use App\Models\Job;
use App\Models\Payment;
use App\Models\Shop;
use App\Models\User;
use App\Services\PayMongo;
use App\Services\ProofExchange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/**
 * The three emails the workflow depends on, and the rule that none of them can
 * break the workflow it belongs to.
 *
 * A proof was uploaded and nobody was told. A customer approved it and the shop
 * found out by refreshing a queue. A payment settled and the customer got
 * nothing. All three are notification gaps rather than logic gaps, which is
 * exactly why they are easy to leave — and why the interesting test is not
 * "was an email sent" but "was the row still written when the mail server was
 * down".
 */
class TransactionalMailTest extends TestCase
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

    private function pendingPayment(): Payment
    {
        $job = $this->quotedJob();

        return Payment::create([
            'job_id' => $job->id,
            'provider' => 'paymongo',
            'intent_id' => 'pi_mail_'.$job->reference,
            'amount' => 203680,
            'processing_fee' => 6592,
            'commission' => 0,
            'status' => 'pending',
        ]);
    }

    private function paidEvent(string $intentId): array
    {
        return [
            'data' => [
                'id' => 'evt_mail',
                'type' => 'event',
                'attributes' => [
                    'type' => 'payment.paid',
                    'data' => [
                        'id' => 'pay_mail',
                        'type' => 'payment',
                        'attributes' => ['payment_intent_id' => $intentId, 'status' => 'paid'],
                    ],
                ],
            ],
        ];
    }

    public function test_sending_a_proof_emails_the_customer(): void
    {
        Mail::fake();

        $job = $this->quotedJob();

        app(ProofExchange::class)->send($job, 'proofs/v1.pdf', 'Check the spelling.');

        Mail::assertSent(ProofSent::class, function (ProofSent $mail) use ($job) {
            return $mail->hasTo('jomar@tarpalink.ph')
                && $mail->job->id === $job->id
                && $mail->proof->version === 1
                && str_contains($mail->render(), 'Check the spelling.');
        });
    }

    public function test_a_proof_is_still_saved_when_the_mail_server_is_down(): void
    {
        Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP is unreachable'));

        $job = $this->quotedJob();

        $proof = app(ProofExchange::class)->send($job, 'proofs/v1.pdf');

        // The upload is the record that matters. Losing it because a courtesy
        // email could not be delivered would be the tail wagging the dog.
        $this->assertDatabaseHas('proofs', ['id' => $proof->id, 'version' => 1]);
        $this->assertSame('awaiting_proof', $job->fresh()->status);
    }

    public function test_approving_a_proof_emails_the_shop_owner(): void
    {
        Mail::fake();

        $job = $this->quotedJob();
        $proof = app(ProofExchange::class)->send($job, 'proofs/v1.pdf');

        $this->actingAs($this->customer())
            ->post(route('jobs.proof.approve', [$job, $proof]))
            ->assertRedirect();

        Mail::assertSent(ProofDecided::class, function (ProofDecided $mail) use ($job) {
            return $mail->hasTo('kevin@inkwell.ph')
                && $mail->approved === true
                && $mail->job->id === $job->id;
        });
    }

    public function test_requesting_changes_emails_the_shop_owner_with_the_note(): void
    {
        Mail::fake();

        $job = $this->quotedJob();
        $proof = app(ProofExchange::class)->send($job, 'proofs/v1.pdf');

        $this->actingAs($this->customer())
            ->post(route('jobs.proof.reject', [$job, $proof]), ['note' => 'The logo is stretched.'])
            ->assertRedirect();

        Mail::assertSent(ProofDecided::class, function (ProofDecided $mail) {
            return $mail->hasTo('kevin@inkwell.ph')
                && $mail->approved === false
                && str_contains((string) $mail->note, 'logo is stretched')
                && str_contains($mail->render(), 'logo is stretched');
        });
    }

    public function test_a_settled_payment_emails_a_receipt(): void
    {
        Mail::fake();

        $payment = $this->pendingPayment();

        (new ProcessPayMongoWebhook($this->paidEvent($payment->intent_id)))
            ->handle(app(PayMongo::class));

        Mail::assertSent(PaymentReceipt::class, function (PaymentReceipt $mail) use ($payment) {
            return $mail->hasTo('jomar@tarpalink.ph')
                && $mail->payment->id === $payment->id
                && str_contains($mail->render(), '₱2,036.80');
        });
    }

    public function test_a_replayed_webhook_does_not_send_a_second_receipt(): void
    {
        Mail::fake();

        $payment = $this->pendingPayment();
        $event = $this->paidEvent($payment->intent_id);

        (new ProcessPayMongoWebhook($event))->handle(app(PayMongo::class));
        (new ProcessPayMongoWebhook($event))->handle(app(PayMongo::class));

        Mail::assertSent(PaymentReceipt::class, 1);
    }

    public function test_a_mail_failure_does_not_stop_a_payment_settling(): void
    {
        Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP is unreachable'));

        $payment = $this->pendingPayment();

        (new ProcessPayMongoWebhook($this->paidEvent($payment->intent_id)))
            ->handle(app(PayMongo::class));

        // A mail problem must never look like a payment problem: the money is
        // recorded, and the job is not retried into a "failed" state.
        $payment->refresh();

        $this->assertSame('paid', $payment->status);
        $this->assertNull($payment->last_error);
    }

    public function test_the_proof_email_links_to_the_job_page(): void
    {
        Mail::fake();

        $job = $this->quotedJob();
        app(ProofExchange::class)->send($job, 'proofs/v1.pdf');

        Mail::assertSent(ProofSent::class, function (ProofSent $mail) use ($job) {
            return str_contains($mail->render(), route('jobs.show', $job));
        });
    }

    protected function tearDown(): void
    {
        // `Proof::DISK` points inside storage; a stray file would leak into the
        // next run and make the "missing artwork" test pass for the wrong reason.
        Storage::disk('proofs')->deleteDirectory('proofs');

        parent::tearDown();
    }
}
