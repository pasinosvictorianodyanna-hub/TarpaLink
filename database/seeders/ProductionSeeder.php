<?php

namespace Database\Seeders;

use App\Models\PageSection;
use App\Models\Plan;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * What a fresh production database needs, and nothing else.
 *
 * `DatabaseSeeder` is demo data — seven shops with their services, jobs and
 * customers with known passwords. It must never run against a live database.
 * This seeder creates only the rows the application cannot start without:
 *
 *   - the plan catalogue (billing reads it; a shop cannot be priced without it)
 *   - the six CMS sections (the landing page falls back to defaults, but an
 *     editor needs something to edit)
 *   - site settings
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
        $this->content();
        $this->settings();
        $this->administrator();

        // The footer pages are page_sections rows (`page.about`, `page.pricing`
        // …) served by PageController. Without them every footer link is a 404,
        // and the site looks broken in a way nothing in the UI explains. This
        // seeder is updateOrCreate throughout, so it is safe to re-run.
        $this->call(PageContentSeeder::class);
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

    private function content(): void
    {
        $sections = [
            ['key' => 'hero', 'label' => 'Homepage hero', 'sort' => 1, 'content' => [
                'eyebrow' => 'Print, priced before you commit',
                'headline' => 'Every tarpaulin, sticker and shirt — priced to the peso.',
                'subheadline' => 'Pick a shop, set your size, see the exact total. No Messenger haggling, no surprise counter price.',
                'primary_label' => 'Find a print shop',
                'primary_link' => '/shops',
            ]],
            ['key' => 'banner', 'label' => 'Announcement banner', 'sort' => 2, 'content' => [
                'text' => 'Free proof on every job over ₱1,500 — approve before we print.',
                'link_label' => 'How proofs work',
                'link' => '/shops',
            ]],
            ['key' => 'featured_shops', 'label' => 'Featured shops', 'sort' => 3, 'content' => [
                'title' => 'Shops near you',
                'subtitle' => 'Verified, priced and rated by customers who actually picked up.',
            ]],
            ['key' => 'testimonials', 'label' => 'Testimonials', 'sort' => 4, 'content' => [
                'title' => 'What customers say',
                'items' => [],
            ]],
            ['key' => 'faq', 'label' => 'FAQ', 'sort' => 5, 'content' => [
                'title' => 'Common questions',
                'items' => [
                    ['q' => 'Do I pay online?', 'a' => 'You can pay with GCash, Maya or card, or pay cash when you pick up. The price is the same either way.'],
                    ['q' => 'Can I change the design after ordering?', 'a' => 'Yes — until you approve the proof. After approval the job goes to press and the artwork is locked.'],
                    ['q' => 'How do I get my print?', 'a' => 'Pick it up at the shop. Every job shows the counter address and the time it will be ready.'],
                ],
            ]],
            ['key' => 'footer', 'label' => 'Footer', 'sort' => 6, 'content' => [
                'blurb' => 'TarpaLink is a print marketplace for the Philippines. Exact prices, approved proofs, pickup at the counter.',
            ]],
        ];

        foreach ($sections as $section) {
            PageSection::updateOrCreate(
                ['key' => $section['key']],
                $section + ['status' => 'published'],
            );
        }
    }

    private function settings(): void
    {
        $settings = [
            'support_email' => env('MAIL_FROM_ADDRESS', 'hello@tarpalink.ph'),
            'platform_name' => env('APP_NAME', 'TarpaLink'),
        ];

        foreach ($settings as $key => $value) {
            SiteSetting::updateOrCreate(['key' => $key], ['value' => $value]);
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
