<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * What a fresh production database needs, and nothing else.
 *
 * `DatabaseSeeder` is demo data — six shops, twenty-one jobs, customers with
 * known passwords. It must never run against a live database. This seeder
 * creates only the rows the application cannot start without:
 *
 *   - the plan catalogue (billing reads it; a shop cannot be priced without it)
 *   - one administrator, from the environment, because /admin is otherwise
 *     unreachable — there is no self-service signup.
 *
 * Every write is updateOrCreate, so running it twice changes nothing.
 */
class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        $this->plans();
        $this->administrator();

        // Storefront copy is not seeded here any more. It belongs to the CMS
        // application, which has its own seeder and its own database; this one
        // fills only what the engine cannot start without.
    }

    private function plans(): void
    {
        $plans = [
            ['code' => 'starter', 'name' => 'Starter', 'price' => 0, 'commission_rate' => 500, 'sort' => 1,
                'blurb' => 'Free forever. You pay 5% of each completed job.',
                'features' => ['Storefront listing', 'Price matrix', 'Proof approval', '5% per completed job']],

            ['code' => 'growth', 'name' => 'Growth', 'price' => 59900, 'commission_rate' => 300, 'sort' => 2,
                'blurb' => '₱599 a month. The rate drops to 3%.',
                'features' => ['Everything in Starter', '3% per completed job', 'Payout ledger', 'Reports export']],

            ['code' => 'pro', 'name' => 'Pro', 'price' => 149900, 'commission_rate' => 150, 'sort' => 3,
                'blurb' => '₱1,499 a month. The rate drops to 1.5%.',
                'features' => ['Everything in Growth', '1.5% per completed job', 'Priority placement', 'Multi-staff access']],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['code' => $plan['code']], $plan);
        }
    }

    /**
     * One administrator, from the environment.
     *
     * Deliberately not defaulted: an account with a known password in
     * production is worse than no account. Without these variables the seeder
     * says so and leaves the panel locked rather than inventing credentials.
     */
    private function administrator(): void
    {
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        if (! $email || ! $password) {
            $this->command?->warn(
                'ADMIN_EMAIL / ADMIN_PASSWORD not set — no administrator created. '
                .'Set them and re-run, or create one with `php artisan tinker`.'
            );

            return;
        }

        $admin = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => env('ADMIN_NAME', 'Administrator'),
                'role' => 'admin',
                'password' => Hash::make($password),
            ],
        );

        $this->command?->info("Administrator ready: {$admin->email}");
    }
}
