<?php

namespace Tests\Feature;

use App\Models\Job;
use App\Models\Payment;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * A delete must never reach money.
 *
 * Both cascades ended at `payments`: delete a shop, its jobs went, and with them
 * every settled payment the ledger and the platform's commission are computed
 * from. Nothing warned. The admin screen has no delete button for a shop, but a
 * button not existing is not a rule — the database was perfectly willing.
 *
 * These tests check the rule where it now lives, which is the foreign key, and
 * then check that the panel agrees with it so the failure is a hidden button
 * rather than a 500.
 */
class DeleteSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function admin(): User
    {
        return User::where('email', 'sofia@tarpalink.ph')->firstOrFail();
    }

    private function shop(): Shop
    {
        return Shop::where('slug', 'inkwell-press')->firstOrFail();
    }

    public function test_deleting_a_shop_that_has_jobs_is_refused_by_the_database(): void
    {
        $paymentsBefore = Payment::count();
        $shop = $this->shop();

        $this->assertGreaterThan(0, $shop->jobs()->count());

        try {
            $shop->delete();
            $this->fail('The database allowed a shop with order history to be deleted.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('FOREIGN KEY', strtoupper($e->getMessage()));
        }

        // Nothing went with it, which is the whole point.
        $this->assertDatabaseHas('shops', ['id' => $shop->id]);
        $this->assertSame($paymentsBefore, Payment::count());
    }

    public function test_deleting_a_job_that_has_a_payment_is_refused_by_the_database(): void
    {
        $job = Job::whereHas('payment')->firstOrFail();
        $paymentsBefore = Payment::count();

        try {
            $job->delete();
            $this->fail('The database allowed a paid job to be deleted.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('FOREIGN KEY', strtoupper($e->getMessage()));
        }

        $this->assertDatabaseHas('jobs', ['id' => $job->id]);
        $this->assertSame($paymentsBefore, Payment::count());
    }

    /**
     * The restriction has to be narrow enough to leave the useful case alone.
     * An abandoned quote with no money on it is exactly what an administrator
     * should be able to clear out.
     */
    public function test_an_abandoned_job_with_no_payment_still_deletes(): void
    {
        $job = Job::whereDoesntHave('payment')->firstOrFail();

        $job->delete();

        $this->assertDatabaseMissing('jobs', ['id' => $job->id]);
    }

    public function test_a_shop_with_no_jobs_still_deletes(): void
    {
        $shop = Shop::create([
            'slug' => 'empty-shop',
            'name' => 'Empty Shop',
            'city' => 'Pasay',
            'address' => '12 Roxas Boulevard',
            'status' => 'pending',
            'commission_rate' => 1000,
        ]);

        $shop->delete();

        $this->assertDatabaseMissing('shops', ['id' => $shop->id]);
    }

    public function test_the_panel_refuses_to_delete_a_shop_that_has_jobs(): void
    {
        $admin = $this->admin();

        $this->assertFalse(Gate::forUser($admin)->allows('delete', $this->shop()));
    }

    public function test_the_panel_refuses_to_delete_a_customer_whose_jobs_carry_payments(): void
    {
        $admin = $this->admin();

        $payer = User::whereHas('jobs.payment')->firstOrFail();

        $this->assertFalse(Gate::forUser($admin)->allows('delete', $payer));
    }

    public function test_the_panel_still_deletes_a_customer_who_never_paid(): void
    {
        $admin = $this->admin();

        $window = User::create([
            'name' => 'Window Shopper',
            'email' => 'window@example.com',
            'role' => 'customer',
            'password' => bcrypt('password'),
        ]);

        $this->assertTrue(Gate::forUser($admin)->allows('delete', $window));
    }

    public function test_an_administrator_still_cannot_delete_themselves(): void
    {
        $admin = $this->admin();

        $this->assertFalse(Gate::forUser($admin)->allows('delete', $admin));
    }
}
