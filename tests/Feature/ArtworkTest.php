<?php

namespace Tests\Feature;

use App\Models\Artwork;
use App\Models\Job;
use App\Models\JobActivity;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The file a customer sends the shop to print.
 *
 * The published privacy policy lists "the artwork file you upload" among the
 * data a job collects, the terms make the customer responsible for it, and the
 * shop-facing pitch promises "jobs that arrive priced and paid with the artwork
 * attached". None of that existed: the only way to tell a shop anything was a
 * 500-character notes field. These tests pin the exchange that was promised.
 */
class ArtworkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        // The real disk is private and local; the fake proves the write went to
        // the `artwork` disk without leaving files behind between runs.
        Storage::fake(Artwork::DISK);
    }

    private function shop(): Shop
    {
        return Shop::where('slug', 'inkwell-press')->firstOrFail();
    }

    private function customer(): User
    {
        return User::where('email', 'jomar@tarpalink.ph')->firstOrFail();
    }

    private function owner(): User
    {
        return User::where('email', 'kevin@inkwell.ph')->firstOrFail();
    }

    /** A job the customer owns and the shop has not printed yet. */
    private function quoted(?User $customer = null): Job
    {
        $customer ??= $this->customer();

        return Job::where('user_id', $customer->id)->where('status', 'quoted')->firstOrFail();
    }

    private function pdf(string $name = 'poster.pdf', int $kilobytes = 40): UploadedFile
    {
        return UploadedFile::fake()->create($name, $kilobytes, 'application/pdf');
    }

    public function test_the_customer_can_send_the_design_to_the_shop(): void
    {
        $job = $this->quoted();

        $this->actingAs($this->customer())
            ->post(route('jobs.artwork.store', $job), ['file' => $this->pdf('flyer-a4.pdf')])
            ->assertRedirect(route('jobs.show', $job))
            ->assertSessionHas('status');

        $artwork = $job->fresh()->artworks()->firstOrFail();

        $this->assertSame('flyer-a4.pdf', $artwork->original_name);
        $this->assertSame('application/pdf', $artwork->mime);
        $this->assertSame($this->customer()->id, $artwork->uploaded_by);
        $this->assertTrue($artwork->exists());
        Storage::disk(Artwork::DISK)->assertExists($artwork->file_path);
    }

    public function test_the_job_records_that_the_file_arrived(): void
    {
        $job = $this->quoted();

        $this->actingAs($this->customer())
            ->post(route('jobs.artwork.store', $job), ['file' => $this->pdf('poster.pdf')]);

        $note = $job->fresh()->activities()->where('kind', JobActivity::NOTE)->firstOrFail();

        $this->assertStringContainsString('poster.pdf', $note->note);
        $this->assertStringContainsString('Artwork sent', $note->note);
    }

    public function test_a_second_file_is_kept_and_becomes_the_current_one(): void
    {
        $job = $this->quoted();

        foreach (['first.pdf', 'second.pdf'] as $name) {
            $this->actingAs($this->customer())
                ->post(route('jobs.artwork.store', $job), ['file' => $this->pdf($name)]);
        }

        $artworks = $job->fresh()->artworks;

        // Both are kept: what the shop received, and when, is the record. The
        // newest is what gets printed.
        $this->assertCount(2, $artworks);
        $this->assertSame('second.pdf', $job->fresh()->latestArtwork()->original_name);
        $this->assertSame('second.pdf', $artworks->first()->original_name);
    }

    public function test_the_replacement_note_says_it_is_a_replacement(): void
    {
        $job = $this->quoted();

        foreach (['first.pdf', 'second.pdf'] as $name) {
            $this->actingAs($this->customer())
                ->post(route('jobs.artwork.store', $job), ['file' => $this->pdf($name)]);
        }

        $notes = $job->fresh()->activities()->where('kind', JobActivity::NOTE)->pluck('note');

        $this->assertTrue($notes->contains(fn (string $n) => str_starts_with($n, 'Artwork sent')));
        $this->assertTrue($notes->contains(fn (string $n) => str_starts_with($n, 'Replacement artwork sent')));
    }

    public function test_another_customer_cannot_send_a_file_to_someone_elses_job(): void
    {
        $job = $this->quoted();
        $stranger = User::where('email', 'renz@example.com')->firstOrFail();

        // 404 rather than 403, so a guessed reference confirms nothing — the
        // same rule the job page and the proof routes follow.
        $this->actingAs($stranger)
            ->post(route('jobs.artwork.store', $job), ['file' => $this->pdf()])
            ->assertNotFound();

        $this->assertCount(0, $job->fresh()->artworks);
    }

    public function test_the_shop_cannot_upload_on_the_customers_behalf(): void
    {
        $job = $this->quoted();

        // The shop may look at the job it is printing, but only the customer
        // decides what gets printed.
        $this->actingAs($this->owner())
            ->post(route('jobs.artwork.store', $job), ['file' => $this->pdf()])
            ->assertForbidden();

        $this->assertCount(0, $job->fresh()->artworks);
    }

    public function test_the_shop_can_download_what_the_customer_sent(): void
    {
        $job = $this->quoted();

        $this->actingAs($this->customer())
            ->post(route('jobs.artwork.store', $job), ['file' => $this->pdf('banner.pdf')]);

        $artwork = $job->fresh()->artworks()->firstOrFail();

        $this->actingAs($this->owner())
            ->get(route('jobs.artwork.file', [$job, $artwork]))
            ->assertOk()
            ->assertDownload('banner.pdf');
    }

    public function test_the_file_is_not_reachable_without_signing_in(): void
    {
        $job = $this->quoted();

        // Written straight to the database rather than uploaded, so no
        // `actingAs` is left set on the test — a guest request is the point.
        Storage::disk(Artwork::DISK)->put('artwork/guest-check.pdf', 'x');

        $artwork = $job->artworks()->create([
            'uploaded_by' => $this->customer()->id,
            'file_path' => 'artwork/guest-check.pdf',
            'original_name' => 'guest-check.pdf',
            'mime' => 'application/pdf',
            'bytes' => 1,
        ]);

        // The route is behind `auth`; a guest is bounced to the sign-in page
        // rather than handed the file.
        $this->get(route('jobs.artwork.file', [$job, $artwork]))
            ->assertRedirect(route('login'));
    }

    public function test_another_customer_cannot_download_the_file(): void
    {
        $job = $this->quoted();

        $this->actingAs($this->customer())
            ->post(route('jobs.artwork.store', $job), ['file' => $this->pdf()]);

        $artwork = $job->fresh()->artworks()->firstOrFail();

        $this->actingAs(User::where('email', 'renz@example.com')->firstOrFail())
            ->get(route('jobs.artwork.file', [$job, $artwork]))
            ->assertNotFound();
    }

    public function test_the_artwork_is_locked_once_the_proof_is_approved(): void
    {
        $job = $this->quoted();
        $job->update(['status' => 'approved']);

        $this->actingAs($this->customer())
            ->post(route('jobs.artwork.store', $job), ['file' => $this->pdf()])
            ->assertStatus(422);

        $this->assertCount(0, $job->fresh()->artworks);
    }

    public function test_a_cancelled_job_takes_no_more_files(): void
    {
        $job = $this->quoted();
        $job->update(['status' => 'cancelled']);

        $this->actingAs($this->customer())
            ->post(route('jobs.artwork.store', $job), ['file' => $this->pdf()])
            ->assertStatus(422);

        $this->assertCount(0, $job->fresh()->artworks);
    }

    public function test_a_job_closed_before_any_file_was_sent_explains_itself(): void
    {
        // The card renders either way, so the empty state has to know whether it
        // is still waiting or has stopped waiting. It used to say "Not sent yet"
        // and "X prints the file you send" on a cancelled order: an instruction
        // with no form under it and no way to ever clear it.
        $job = $this->quoted();
        $job->update(['status' => 'cancelled']);

        $html = $this->actingAs($this->customer())
            ->get(route('jobs.show', $job))
            ->assertOk()
            ->assertSee('closed before you sent a design')
            ->assertDontSee('Not sent yet')
            ->assertDontSee('prints the file you send')
            ->getContent();

        $this->assertStringNotContainsString(
            route('jobs.artwork.store', $job),
            $html,
            'A cancelled job still offers an upload form.',
        );
    }

    public function test_a_file_the_shop_cannot_open_is_refused_with_advice(): void
    {
        $job = $this->quoted();

        $response = $this->actingAs($this->customer())
            ->post(route('jobs.artwork.store', $job), [
                'file' => UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload'),
            ]);

        $response->assertSessionHasErrors('file');

        $this->assertStringContainsString(
            'PNG, JPEG, WebP or PDF',
            session('errors')->first('file'),
        );

        $this->assertCount(0, $job->fresh()->artworks);
    }

    public function test_the_job_page_asks_for_the_design_while_it_has_none(): void
    {
        $job = $this->quoted();

        $this->actingAs($this->customer())
            ->get(route('jobs.show', $job))
            ->assertOk()
            ->assertSee('is waiting for your design')
            ->assertSee('Not sent yet');
    }

    public function test_the_job_page_lists_the_file_once_it_arrives(): void
    {
        $job = $this->quoted();

        $this->actingAs($this->customer())
            ->post(route('jobs.artwork.store', $job), ['file' => $this->pdf('tarpaulin-final.pdf')]);

        $this->actingAs($this->customer())
            ->get(route('jobs.show', $job))
            ->assertOk()
            ->assertSee('tarpaulin-final.pdf')
            ->assertSee('Download')
            ->assertDontSee('is waiting for your design');
    }

    public function test_the_shop_console_shows_the_file_before_the_proof_form(): void
    {
        $job = $this->quoted();

        $this->actingAs($this->customer())
            ->post(route('jobs.artwork.store', $job), ['file' => $this->pdf('shop-copy.pdf')]);

        $html = $this->actingAs($this->owner())
            ->get(route('console.jobs.show', $job))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString("Customer's artwork", $html);
        $this->assertStringContainsString('shop-copy.pdf', $html);

        // The input comes before the output: a shop reads this page top to
        // bottom before it starts, so the file sits above the proof form.
        $this->assertLessThan(
            strpos($html, 'Send a proof'),
            strpos($html, 'Customer&#039;s artwork') ?: strpos($html, "Customer's artwork"),
        );
    }

    public function test_the_console_says_so_when_nothing_has_been_sent(): void
    {
        $job = $this->quoted();

        $this->actingAs($this->owner())
            ->get(route('console.jobs.show', $job))
            ->assertOk()
            ->assertSee('The customer has not sent a design yet');
    }

    public function test_a_customer_with_no_artwork_can_still_see_the_job(): void
    {
        // The page must not 500 on a job whose file list is empty — the empty
        // state is the common case for a brand-new order.
        $job = $this->quoted();

        $this->assertCount(0, $job->artworks);

        $this->actingAs($this->customer())
            ->get(route('jobs.show', $job))
            ->assertOk();
    }

    /**
     * The shop has to open each job to find out whether a design arrived, and the
     * email the upload sends is best-effort (#82) — so the queue itself has to
     * answer the question the owner is asking it.
     */
    public function test_the_job_queue_says_whether_the_design_has_arrived(): void
    {
        $job = $this->quoted();

        $before = $this->queueRow($job);

        $this->assertNotSame('', $before, 'The job should be in the queue.');
        $this->assertStringContainsString('not sent', $before);

        $this->actingAs($this->customer())
            ->post(route('jobs.artwork.store', $job), ['file' => $this->pdf('queue.pdf')]);

        $after = $this->queueRow($job);

        $this->assertStringNotContainsString('not sent', $after, 'The badge must stop saying "not sent".');
        $this->assertStringContainsString('sent', $after);
    }

    /** A job that has been collected is no longer waiting on anything. */
    public function test_the_queue_does_not_nag_about_a_closed_job(): void
    {
        $job = $this->quoted();
        $job->forceFill(['status' => 'cancelled'])->save();

        $row = $this->queueRow($job);

        $this->assertNotSame('', $row, 'A cancelled job still belongs in the list.');
        $this->assertStringNotContainsString('not sent', $row);
    }

    /**
     * The board is where a shop looks while it works, so the same fact belongs
     * there — and it stops mattering once a proof has gone out, because the file
     * is locked at that point and its absence is no longer something to chase.
     */
    public function test_the_board_marks_a_job_waiting_for_the_customers_design(): void
    {
        $job = $this->quoted();

        $before = $this->boardCard($job);

        $this->assertNotSame('', $before, 'A quoted job belongs on the board.');
        $this->assertStringContainsString('no design', $before);

        $this->actingAs($this->customer())
            ->post(route('jobs.artwork.store', $job), ['file' => $this->pdf('board.pdf')]);

        $after = $this->boardCard($job);

        $this->assertStringNotContainsString('no design', $after);
        $this->assertStringContainsString('design', $after);
    }

    /** The queue row for one job, isolated so an assertion cannot read a neighbour. */
    private function queueRow(Job $job): string
    {
        $html = $this->actingAs($this->owner())
            ->get(route('console.jobs'))
            ->assertOk()
            ->getContent();

        preg_match_all('/<tr>.*?<\/tr>/s', $html, $rows);

        foreach ($rows[0] as $row) {
            if (str_contains($row, $job->reference)) {
                return $row;
            }
        }

        return '';
    }

    /** The board card for one job, for the same reason. */
    private function boardCard(Job $job): string
    {
        $html = $this->actingAs($this->owner())
            ->get(route('console.board'))
            ->assertOk()
            ->getContent();

        preg_match_all('/<a\s+class="board-card".*?<\/a>/s', $html, $cards);

        foreach ($cards[0] as $card) {
            if (str_contains($card, $job->reference)) {
                return $card;
            }
        }

        return '';
    }
}
