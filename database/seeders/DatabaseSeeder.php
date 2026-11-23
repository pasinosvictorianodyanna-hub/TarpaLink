<?php

namespace Database\Seeders;

use App\Models\Invoice;
use App\Models\Job;
use App\Models\PageSection;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\PriceOption;
use App\Models\PriceTier;
use App\Models\Proof;
use App\Models\QuantityDiscount;
use App\Models\Service;
use App\Models\Shop;
use App\Models\SiteSetting;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Billing;
use App\Services\PriceCalculator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->plans();

        $shops = $this->shops();

        $this->people($shops);
        $this->subscriptions($shops);
        $this->content();

        // The proof rows below point at `proofs/sample.pdf`. Without the file,
        // every "View proof artwork" link in the seeded database is a 404 — which
        // reads as a broken feature rather than as a stand-in for real artwork.
        $this->sampleProof();

        // Kept as its own seeder because it is idempotent: the footer copy can be
        // refreshed on a live database without rebuilding jobs and payments.
        $this->call(\Database\Seeders\PageContentSeeder::class);

        // Everyone in the seed set their password when the account was created.
        // Leaving it null would make every profile screen read "Never changed",
        // which looks like a bug in the demo rather than a fact about the data.
        User::query()->update(['password_changed_at' => now()->subMonths(3)]);
    }

    /**
     * A one-page PDF standing in for the artwork a shop would upload.
     *
     * Written by hand rather than shipped as a binary fixture: it is a dozen
     * lines, it is obviously a placeholder, and it keeps the repository free of
     * a blob nobody can diff. The cross-reference table is real, so browsers
     * open it instead of guessing at a repair.
     */
    private function sampleProof(): void
    {
        $disk = Storage::disk(Proof::DISK);

        if ($disk->exists('proofs/sample.pdf')) {
            return;
        }

        $text = "BT /F1 26 Tf 60 760 Td (TarpaLink) Tj ET\n"
            ."BT /F1 14 Tf 60 730 Td (Sample proof artwork) Tj ET\n"
            ."BT /F1 11 Tf 60 700 Td (This file stands in for the artwork a shop uploads.) Tj ET\n";

        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            "<< /Length ".strlen($text)." >>\nstream\n".$text."endstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $index => $body) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n".$body."\nendobj\n";
        }

        $startxref = strlen($pdf);
        $size = count($objects) + 1;

        $pdf .= "xref\n0 {$size}\n0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= str_pad((string) $offset, 10, '0', STR_PAD_LEFT)." 00000 n \n";
        }

        $pdf .= "trailer\n<< /Size {$size} /Root 1 0 R >>\nstartxref\n{$startxref}\n%%EOF\n";

        $disk->put('proofs/sample.pdf', $pdf);
    }

    /** The three subscription tiers. Rates are basis points. */
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
            \App\Models\Plan::updateOrCreate(['code' => $plan['code']], $plan);
        }
    }

    /** Six shops with deliberately different matrices. */
    private function shops(): array
    {
        $blueprint = [
            [
                'name' => 'Inkwell Press', 'slug' => 'inkwell-press', 'city' => 'Quezon City',
                'address' => '24 Sct. Borromeo St, Diliman', 'accent' => 'ink',
                'plan' => 'growth', 'rate' => 300,
                'rating' => 4.9, 'jobs_done' => 1284, 'phone' => '+63 917 442 8891',
                'description' => 'Offset and large-format since 2009. Tarpaulin, stickers, flyers and DTF shirts.',
                'discounts' => [[3, 5], [10, 10], [50, 15]],
                'services' => [
                    ['name' => 'Tarpaulin', 'slug' => 'tarpaulin', 'unit' => 'sqft', 'blurb' => '13oz vinyl, outdoor rated',
                        'tiers' => [['Small (≤16in)', 0, 16, 2800], ['Medium (≤48in)', 16, 48, 2200], ['Large (≤96in)', 48, 96, 1900], ['Extra large', 96, null, 1700]],
                        'options' => [['Eyelets', 'pc', 1000], ['Hemming', 'ft', 1500], ['Matte laminate', 'sqft', 400]]],
                    ['name' => 'Stickers', 'slug' => 'stickers', 'unit' => 'pc', 'blurb' => 'Die-cut vinyl, waterproof',
                        'tiers' => [['2×2 in', 0, 2, 1500], ['3×3 in', 2, 3, 2200], ['4×4 in', 3, 4, 3200]],
                        'options' => [['Lamination', 'pc', 800], ['Die-cut', 'pc', 500]]],
                    ['name' => 'Flyers', 'slug' => 'flyers', 'unit' => 'pc', 'blurb' => 'A4/A5, 120gsm',
                        'tiers' => [['A5', 0, 8.3, 400], ['A4', 8.3, 11.7, 600]],
                        'options' => [['Matte finish', 'pc', 200]]],
                    ['name' => 'DTF shirts', 'slug' => 'dtf-shirts', 'unit' => 'pc', 'blurb' => 'Full colour transfer',
                        'tiers' => [['Front only', 0, 12, 18000], ['Front + back', 12, 24, 26000]],
                        'options' => [['Extra print', 'pc', 8000]]],
                ],
            ],
            [
                'name' => 'Halftone Hub', 'slug' => 'halftone-hub', 'city' => 'Makati',
                'address' => 'Unit 4B, Chino Roces Ave', 'accent' => 'cyan',
                'plan' => 'pro', 'rate' => 150,
                'rating' => 4.7, 'jobs_done' => 842, 'phone' => '+63 918 220 1144',
                'description' => 'Small-format specialists. Stickers, labels and business cards.',
                'discounts' => [[5, 8], [20, 12]],
                'services' => [
                    ['name' => 'Stickers', 'slug' => 'stickers', 'unit' => 'pc', 'blurb' => 'Die-cut vinyl, waterproof',
                        'tiers' => [['2×2 in', 0, 2, 1200], ['3×3 in', 2, 3, 1900], ['4×4 in', 3, 4, 2800]],
                        'options' => [['Lamination', 'pc', 700], ['Die-cut', 'pc', 400]]],
                    ['name' => 'Business cards', 'slug' => 'business-cards', 'unit' => 'pc', 'blurb' => '350gsm, matte',
                        'tiers' => [['Standard', 0, 4, 390], ['Rounded corner', 4, 8, 450]],
                        'options' => [['Spot UV', 'pc', 150]]],
                    ['name' => 'Flyers', 'slug' => 'flyers', 'unit' => 'pc', 'blurb' => 'A4/A5, 120gsm',
                        'tiers' => [['A5', 0, 8.3, 350], ['A4', 8.3, 11.7, 520]],
                        'options' => [['Matte finish', 'pc', 180]]],
                ],
            ],
            [
                'name' => 'Studio Sampaguita', 'slug' => 'studio-sampaguita', 'city' => 'Pasig',
                'address' => '12 Meralco Ave, Ortigas', 'accent' => 'magenta',
                'plan' => 'starter', 'rate' => 500,
                'rating' => 4.8, 'jobs_done' => 610, 'phone' => '+63 916 771 2030',
                'description' => 'Design-led print studio. Tarpaulin and large stickers.',
                'discounts' => [[3, 5], [25, 12]],
                'services' => [
                    ['name' => 'Tarpaulin', 'slug' => 'tarpaulin', 'unit' => 'sqft', 'blurb' => '13oz vinyl, outdoor rated',
                        'tiers' => [['Small (≤16in)', 0, 16, 3200], ['Medium (≤48in)', 16, 48, 2600], ['Large (≤96in)', 48, 96, 2300]],
                        'options' => [['Eyelets', 'pc', 1200], ['Hemming', 'ft', 1800]]],
                    ['name' => 'Stickers', 'slug' => 'stickers', 'unit' => 'pc', 'blurb' => 'Die-cut vinyl, waterproof',
                        'tiers' => [['2×2 in', 0, 2, 1800], ['3×3 in', 2, 3, 2600]],
                        'options' => [['Lamination', 'pc', 900]]],
                ],
            ],
            [
                'name' => 'Presswork MNL', 'slug' => 'presswork-mnl', 'city' => 'Manila',
                'address' => '88 CM Recto Ave, Binondo', 'accent' => 'yellow',
                'plan' => 'starter', 'rate' => 500,
                'rating' => 4.4, 'jobs_done' => 2107, 'phone' => '+63 920 118 4477',
                'description' => 'Volume printer. Cheapest tarpaulin in the district, cash on pickup.',
                'discounts' => [[10, 10], [50, 20], [100, 25]],
                'services' => [
                    ['name' => 'Tarpaulin', 'slug' => 'tarpaulin', 'unit' => 'sqft', 'blurb' => '13oz vinyl, outdoor rated',
                        'tiers' => [['Small (≤16in)', 0, 16, 2200], ['Medium (≤48in)', 16, 48, 1750], ['Large (≤96in)', 48, 96, 1500], ['Extra large', 96, null, 1350]],
                        'options' => [['Eyelets', 'pc', 800], ['Hemming', 'ft', 1200]]],
                    ['name' => 'Flyers', 'slug' => 'flyers', 'unit' => 'pc', 'blurb' => 'A4/A5, 120gsm',
                        'tiers' => [['A5', 0, 8.3, 300], ['A4', 8.3, 11.7, 450]],
                        'options' => [['Matte finish', 'pc', 150]]],
                ],
            ],
            [
                'name' => 'Tinta & Co.', 'slug' => 'tinta-and-co', 'city' => 'Caloocan',
                'address' => '5 Samson Rd, Monumento', 'accent' => 'yellow',
                'plan' => 'growth', 'rate' => 300,
                'rating' => 4.6, 'jobs_done' => 395, 'phone' => '+63 915 662 8890',
                'description' => 'Apparel-first shop. DTF shirts and heat transfer.',
                'discounts' => [[6, 8], [24, 15]],
                'services' => [
                    ['name' => 'DTF shirts', 'slug' => 'dtf-shirts', 'unit' => 'pc', 'blurb' => 'Full colour transfer',
                        'tiers' => [['Front only', 0, 12, 16500], ['Front + back', 12, 24, 24000]],
                        'options' => [['Extra print', 'pc', 7000], ['Name print', 'pc', 3500]]],
                    ['name' => 'Stickers', 'slug' => 'stickers', 'unit' => 'pc', 'blurb' => 'Die-cut vinyl, waterproof',
                        'tiers' => [['2×2 in', 0, 2, 1400], ['3×3 in', 2, 3, 2000]],
                        'options' => [['Lamination', 'pc', 600]]],
                ],
            ],
            [
                'name' => 'Northline Print', 'slug' => 'northline-print', 'city' => 'Valenzuela',
                'address' => '31 MacArthur Hwy, Karuhatan', 'accent' => 'cyan',
                'plan' => 'starter', 'rate' => 500,
                'rating' => 4.3, 'jobs_done' => 508, 'phone' => '+63 917 335 6612',
                'description' => 'Neighbourhood print shop. Tarpaulin, flyers and shirts.',
                'discounts' => [[5, 6], [30, 12]],
                'services' => [
                    ['name' => 'Tarpaulin', 'slug' => 'tarpaulin', 'unit' => 'sqft', 'blurb' => '13oz vinyl, outdoor rated',
                        'tiers' => [['Small (≤16in)', 0, 16, 2600], ['Medium (≤48in)', 16, 48, 2000], ['Large (≤96in)', 48, 96, 1800]],
                        'options' => [['Eyelets', 'pc', 900], ['Hemming', 'ft', 1400]]],
                    ['name' => 'Flyers', 'slug' => 'flyers', 'unit' => 'pc', 'blurb' => 'A4/A5, 120gsm',
                        'tiers' => [['A5', 0, 8.3, 380], ['A4', 8.3, 11.7, 550]],
                        'options' => [['Matte finish', 'pc', 200]]],
                    ['name' => 'DTF shirts', 'slug' => 'dtf-shirts', 'unit' => 'pc', 'blurb' => 'Full colour transfer',
                        'tiers' => [['Front only', 0, 12, 17500], ['Front + back', 12, 24, 25000]],
                        'options' => [['Extra print', 'pc', 7500]]],
                ],
            ],

            // Still waiting for admission. It has a catalogue and an owner but no
            // jobs and no payouts — approving it in the admin panel is what turns
            // it into a live storefront, and rejecting it locks the owner out.
            [
                'name' => 'Meridian Press', 'slug' => 'meridian-press', 'city' => 'Quezon City',
                'address' => '8 Maginhawa St, Sikatuna Village', 'accent' => 'yellow',
                'plan' => 'starter', 'rate' => 500, 'status' => 'pending',
                'rating' => 0, 'jobs_done' => 0, 'phone' => '+63 915 660 2244',
                'description' => 'New shop. Large-format tarpaulin and event backdrops.',
                'discounts' => [[5, 5]],
                'services' => [
                    ['name' => 'Tarpaulin', 'slug' => 'tarpaulin', 'unit' => 'sqft', 'blurb' => '13oz vinyl, outdoor rated',
                        'tiers' => [['Small (≤16in)', 0, 16, 2700], ['Medium (≤48in)', 16, 48, 2100], ['Large (≤96in)', 48, 96, 1850]],
                        'options' => [['Eyelets', 'pc', 950], ['Hemming', 'ft', 1450]]],
                ],
            ],
        ];

        $created = [];

        foreach ($blueprint as $data) {
            $shop = Shop::create([
                'name' => $data['name'],
                'slug' => $data['slug'],
                'city' => $data['city'],
                'address' => $data['address'],
                'phone' => $data['phone'],
                'description' => $data['description'],
                'accent' => $data['accent'],
                'status' => $data['status'] ?? 'active',
                'approved_at' => ($data['status'] ?? 'active') === 'active' ? now()->subMonths(6) : null,
                'plan' => $data['plan'],
                'commission_rate' => $data['rate'],
                'rating' => $data['rating'],
                'jobs_done' => $data['jobs_done'],
                'lat' => $this->coordinates()[$data['slug']][0],
                'lng' => $this->coordinates()[$data['slug']][1],
                // What the directory filter rail reads. Kept in one map instead of
                // seven more keys on every blueprint entry, so the shop list stays
                // readable as a list of shops rather than a list of booleans.
                ...($this->directoryTraits()[$data['slug']] ?? []),
            ]);

            foreach ($data['discounts'] as [$minQty, $percent]) {
                QuantityDiscount::create([
                    'shop_id' => $shop->id,
                    'min_qty' => $minQty,
                    'percent' => $percent,
                ]);
            }

            foreach ($data['services'] as $sIndex => $serviceData) {
                $service = Service::create([
                    'shop_id' => $shop->id,
                    'name' => $serviceData['name'],
                    'slug' => $serviceData['slug'],
                    'unit' => $serviceData['unit'],
                    'blurb' => $serviceData['blurb'],
                    'sort' => $sIndex,
                ]);

                foreach ($serviceData['tiers'] as $tIndex => [$label, $min, $max, $price]) {
                    PriceTier::create([
                        'service_id' => $service->id,
                        'label' => $label,
                        'min_width' => $min,
                        'max_width' => $max,
                        'unit_price' => $price,
                        'sort' => $tIndex,
                    ]);
                }

                foreach ($serviceData['options'] as $oIndex => [$name, $unit, $price]) {
                    PriceOption::create([
                        'service_id' => $service->id,
                        'name' => $name,
                        'unit' => $unit,
                        'price' => $price,
                        'sort' => $oIndex,
                    ]);
                }
            }

            $created[] = $shop;
        }

        return $created;
    }

    /**
     * Real subscriptions, and the invoices they produce.
     *
     * `shops.plan` is only a label. Nothing is ever billed without a
     * `subscriptions` row, because `issueDueInvoices()` walks that table — so
     * this seed used to show three shops on Growth and Pro, ₱0 of subscription
     * revenue, and an empty Invoices screen in the admin panel.
     *
     * The bills are produced by the real billing service rather than written by
     * hand, so the seed exercises the same path a live upgrade takes: the period
     * arithmetic, the numbering, and the seven-day due date.
     *
     * @param  array<int, Shop>  $shops
     */
    private function subscriptions(array $shops): void
    {
        $billing = app(Billing::class);

        // Spread the anniversaries so the three bills land in three states: one
        // already collected, two overdue, one still inside its seven days. A
        // seed where every invoice is identical proves only that the column can
        // hold a number.
        $startedDaysAgo = [
            'inkwell-press' => 45,
            'tinta-and-co' => 20,
            'halftone-hub' => 3,
        ];

        foreach ($shops as $shop) {
            $plan = Plan::where('code', $shop->plan)->first();

            // A free plan has nothing to invoice, and a pending shop cannot use
            // the console yet — billing a shop before it is open would be the
            // same mistake as creating a shop before it is approved.
            if ($plan === null || $plan->isFree() || $shop->status !== 'active') {
                continue;
            }

            Subscription::create([
                'shop_id' => $shop->id,
                'plan_id' => $plan->id,
                'status' => 'active',
                'started_at' => now()->subDays($startedDaysAgo[$shop->slug] ?? 30),
            ]);
        }

        $billing->issueDueInvoices();

        // One collected, so the admin's subscription figure is not zero and the
        // ledger shows the difference between "issued" and "paid".
        $collected = Invoice::where('status', Invoice::OPEN)->orderBy('id')->first();

        if ($collected !== null) {
            $billing->recordPayment($collected, User::where('role', 'admin')->first());
        }
    }

    /**
     * Turnaround and service options, per shop.
     *
     * The values are deliberately spread so every filter in the rail has a
     * non-empty result — a filter that can only ever return nothing is a
     * filter nobody can test.
     *
     * @return array<string, array<string, mixed>>
     */
    private function directoryTraits(): array
    {
        return [
            'inkwell-press' => ['turnaround_hours' => 24, 'accepts_rush' => true, 'offers_delivery' => true, 'accepts_gcash' => true],
            'halftone-hub' => ['turnaround_hours' => 48, 'accepts_rush' => false, 'accepts_gcash' => true],
            'studio-sampaguita' => ['turnaround_hours' => 24, 'accepts_rush' => false, 'offers_design' => true],
            'presswork-mnl' => ['turnaround_hours' => 72, 'accepts_rush' => false, 'accepts_gcash' => true],
            'northline-print' => ['turnaround_hours' => 8, 'accepts_rush' => true, 'offers_delivery' => true, 'accepts_gcash' => true],
            'tinta-and-co' => ['turnaround_hours' => 48, 'accepts_rush' => false, 'offers_delivery' => true],
            'meridian-press' => ['turnaround_hours' => 24, 'accepts_rush' => false],
        ];
    }

    /**
     * Where each shop actually is, in decimal degrees.
     *
     * These used to be a synthetic diagonal (`14.55 + index * 0.021`) which put a
     * Quezon City shop 15 km from Quezon City. The card shows a distance, and a
     * distance nobody can believe is worse than no distance at all, so each shop
     * now sits on its own city. Offsets are small enough to stay inside the city
     * and large enough that two shops in the same city never stack on the map.
     */
    private function coordinates(): array
    {
        return [
            'inkwell-press' => [14.6400, 121.0550],      // Diliman, Quezon City
            'halftone-hub' => [14.5547, 121.0244],       // Chino Roces, Makati
            'studio-sampaguita' => [14.5764, 121.0851],  // Ortigas, Pasig
            'presswork-mnl' => [14.5995, 120.9842],      // Binondo, Manila
            'tinta-and-co' => [14.6488, 120.9830],       // Monumento, Caloocan
            'northline-print' => [14.7011, 120.9830],    // Karuhatan, Valenzuela
            'meridian-press' => [14.6350, 121.0620],     // Sikatuna, Quezon City
        ];
    }

    private function people(array $shops): void
    {
        $jomar = User::create([
            'name' => 'Jomar Mendoza',
            'email' => 'jomar@tarpalink.ph',
            'phone' => '+63 917 442 8891',
            'city' => 'Quezon City',
            'role' => 'customer',
            'password' => bcrypt('password'),
        ]);

        User::create([
            'name' => 'Sofia Alvarez',
            'email' => 'sofia@tarpalink.ph',
            'role' => 'admin',
            'city' => 'Makati',
            'password' => bcrypt('password'),
        ]);

        $owner = User::create([
            'name' => 'Kevin Cruz',
            'email' => 'kevin@inkwell.ph',
            'role' => 'shop_owner',
            'city' => 'Quezon City',
            'password' => bcrypt('password'),
        ]);

        $shops[0]->staff()->attach($owner->id, ['role' => 'owner']);

        $otherOwners = [
            ['slug' => 'halftone-hub', 'name' => 'Marco Santos', 'email' => 'marco@halftone.ph', 'city' => 'Makati'],
            ['slug' => 'studio-sampaguita', 'name' => 'Clara Reyes', 'email' => 'clara@sampaguita.ph', 'city' => 'Pasig'],
            ['slug' => 'presswork-mnl', 'name' => 'Arturo Tan', 'email' => 'arturo@presswork.ph', 'city' => 'Manila'],
            ['slug' => 'tinta-and-co', 'name' => 'Bianca Lim', 'email' => 'bianca@tinta.ph', 'city' => 'Caloocan'],
            ['slug' => 'northline-print', 'name' => 'Paolo Garcia', 'email' => 'paolo@northline.ph', 'city' => 'Valenzuela'],
        ];

        foreach ($otherOwners as $meta) {
            $shopOwner = User::create([
                'name' => $meta['name'],
                'email' => $meta['email'],
                'role' => 'shop_owner',
                'city' => $meta['city'],
                'password' => bcrypt('password'),
            ]);

            collect($shops)->firstWhere('slug', $meta['slug'])
                ?->staff()->attach($shopOwner->id, ['role' => 'owner']);
        }

        // The owner of the shop that is still pending. He can log in to the site
        // but not to the shop panel until an admin approves Meridian Press.
        $pendingOwner = User::create([
            'name' => 'Lia Domingo',
            'email' => 'lia@meridian.ph',
            'role' => 'shop_owner',
            'city' => 'Quezon City',
            'password' => bcrypt('password'),
        ]);

        collect($shops)->firstWhere('slug', 'meridian-press')
            ?->staff()->attach($pendingOwner->id, ['role' => 'owner']);

        $renz = User::create([
            'name' => 'Renz Cruz',
            'email' => 'renz@example.com',
            'phone' => '0918 220 1144',
            'city' => 'Makati',
            'role' => 'customer',
            'password' => bcrypt('password'),
        ]);

        $marisol = User::create([
            'name' => 'Marisol Reyes',
            'email' => 'marisol@example.com',
            'phone' => '0917 553 0088',
            'city' => 'Pasig',
            'role' => 'customer',
            'password' => bcrypt('password'),
        ]);

        $dennis = User::create([
            'name' => 'Dennis Lim',
            'email' => 'dennis@example.com',
            'phone' => '0920 771 4412',
            'city' => 'Manila',
            'role' => 'customer',
            'password' => bcrypt('password'),
        ]);

        $aira = User::create([
            'name' => 'Aira Villanueva',
            'email' => 'aira@example.com',
            'phone' => '0916 208 9931',
            'city' => 'Quezon City',
            'role' => 'customer',
            'password' => bcrypt('password'),
        ]);

        $calculator = app(PriceCalculator::class);

        // Every shop gets jobs across several statuses, so the production board
        // has something to drag on a fresh clone. Collected jobs carry past dates
        // so the reports and payout ledger have history to sum.
        $jobs = [
            // Inkwell Press — the shop panel demo account.
            ['shop' => 0, 'service' => 0, 'w' => 36, 'h' => 72, 'qty' => 1, 'status' => 'quoted', 'user' => $jomar, 'due' => '+6 days', 'options' => [[0, 6], [1, 12]]],
            ['shop' => 0, 'service' => 3, 'w' => 12, 'h' => 12, 'qty' => 24, 'status' => 'awaiting_proof', 'user' => $renz, 'due' => '+5 days', 'options' => []],
            ['shop' => 0, 'service' => 0, 'w' => 48, 'h' => 96, 'qty' => 3, 'status' => 'approved', 'user' => $jomar, 'due' => '+3 days', 'options' => [[0, 8], [1, 16]]],
            ['shop' => 0, 'service' => 1, 'w' => 3, 'h' => 3, 'qty' => 250, 'status' => 'in_production', 'user' => $marisol, 'due' => '+4 days', 'options' => [[0, 250]]],
            ['shop' => 0, 'service' => 0, 'w' => 48, 'h' => 96, 'qty' => 2, 'status' => 'ready', 'user' => $dennis, 'due' => '+2 days', 'options' => [[0, 8]]],
            ['shop' => 0, 'service' => 2, 'w' => 8.3, 'h' => 11.7, 'qty' => 500, 'status' => 'collected', 'user' => $jomar, 'due' => '-6 days', 'options' => [[0, 500]]],
            ['shop' => 0, 'service' => 0, 'w' => 24, 'h' => 36, 'qty' => 4, 'status' => 'collected', 'user' => $aira, 'due' => '-13 days', 'options' => [[0, 10], [1, 24]]],

            // Collected earlier this week. Without them the seven-day chart is
            // one bar and six zeroes, which reads as a broken chart rather than
            // a quiet week — and the seed exists to show the screen working.
            // `paid` is set explicitly so the bars land on different days
            // instead of all clustering two days before their pickup date.
            ['shop' => 0, 'service' => 0, 'w' => 36, 'h' => 48, 'qty' => 1, 'status' => 'collected', 'user' => $marisol, 'due' => '-2 days', 'options' => [[0, 6]], 'paid' => '-3 days'],
            ['shop' => 0, 'service' => 3, 'w' => 12, 'h' => 12, 'qty' => 50, 'status' => 'collected', 'user' => $dennis, 'due' => '-3 days', 'options' => [], 'paid' => '-4 days'],
            ['shop' => 0, 'service' => 0, 'w' => 24, 'h' => 36, 'qty' => 2, 'status' => 'collected', 'user' => $jomar, 'due' => '-5 days', 'options' => [[0, 10]], 'paid' => '-6 days'],

            // Two jobs on the counter today, so the dashboard's "Due today" card
            // and its subheading have something true to say on a fresh clone.
            // The ready one was paid this morning — that is what makes "Revenue
            // today" non-zero without inventing money from nowhere.
            ['shop' => 0, 'service' => 0, 'w' => 24, 'h' => 36, 'qty' => 2, 'status' => 'in_production', 'user' => $jomar, 'due' => 'today', 'options' => [[0, 6]]],
            ['shop' => 0, 'service' => 1, 'w' => 3, 'h' => 3, 'qty' => 100, 'status' => 'ready', 'user' => $renz, 'due' => 'today', 'options' => [], 'paid' => 'today'],

            // Halftone Hub — pro plan, so a 1.5% take rate on its payouts.
            ['shop' => 1, 'service' => 0, 'w' => 4, 'h' => 4, 'qty' => 500, 'status' => 'awaiting_proof', 'user' => $marisol, 'due' => '+4 days', 'options' => []],
            ['shop' => 1, 'service' => 1, 'w' => 3.5, 'h' => 2, 'qty' => 1000, 'status' => 'collected', 'user' => $dennis, 'due' => '-9 days', 'options' => [[0, 1000]]],
            ['shop' => 1, 'service' => 2, 'w' => 8.3, 'h' => 11.7, 'qty' => 300, 'status' => 'in_production', 'user' => $aira, 'due' => '+3 days', 'options' => [[0, 300]]],

            // Studio Sampaguita
            ['shop' => 2, 'service' => 0, 'w' => 36, 'h' => 72, 'qty' => 1, 'status' => 'quoted', 'user' => $renz, 'due' => '+6 days', 'options' => [[0, 6], [1, 12]]],
            ['shop' => 2, 'service' => 1, 'w' => 3, 'h' => 3, 'qty' => 120, 'status' => 'ready', 'user' => $marisol, 'due' => '+1 days', 'options' => [[0, 120]]],
            ['shop' => 2, 'service' => 0, 'w' => 16, 'h' => 24, 'qty' => 2, 'status' => 'collected', 'user' => $dennis, 'due' => '-18 days', 'options' => [[0, 6]]],

            // Presswork MNL — volume printer, big quantity discounts.
            ['shop' => 3, 'service' => 0, 'w' => 24, 'h' => 36, 'qty' => 2, 'status' => 'approved', 'user' => $jomar, 'due' => '+3 days', 'options' => [[0, 10]]],
            ['shop' => 3, 'service' => 0, 'w' => 48, 'h' => 96, 'qty' => 6, 'status' => 'collected', 'user' => $aira, 'due' => '-11 days', 'options' => [[0, 20], [1, 48]]],
            ['shop' => 3, 'service' => 1, 'w' => 8.3, 'h' => 11.7, 'qty' => 1000, 'status' => 'collected', 'user' => $dennis, 'due' => '-20 days', 'options' => []],
            ['shop' => 3, 'service' => 1, 'w' => 8.3, 'h' => 11.7, 'qty' => 200, 'status' => 'cancelled', 'user' => $marisol, 'due' => '-4 days', 'options' => []],

            // Tinta & Co. — apparel.
            ['shop' => 4, 'service' => 0, 'w' => 12, 'h' => 12, 'qty' => 12, 'status' => 'collected', 'user' => $renz, 'due' => '-6 days', 'options' => [[0, 12]]],
            ['shop' => 4, 'service' => 0, 'w' => 12, 'h' => 24, 'qty' => 30, 'status' => 'in_production', 'user' => $aira, 'due' => '+5 days', 'options' => [[0, 30], [1, 30]]],

            // Northline Print
            ['shop' => 5, 'service' => 0, 'w' => 48, 'h' => 96, 'qty' => 1, 'status' => 'quoted', 'user' => $jomar, 'due' => '+7 days', 'options' => [[0, 8]]],
            ['shop' => 5, 'service' => 2, 'w' => 12, 'h' => 12, 'qty' => 20, 'status' => 'collected', 'user' => $dennis, 'due' => '-15 days', 'options' => [[0, 20]]],
        ];

        // Two older collected jobs per trading shop, so the revenue chart has
        // more than one week of history to draw instead of a single bar.
        foreach ($shops as $index => $shop) {
            if (! $shop->isActive()) {
                continue;
            }

            // Size from the shop's own cheapest tier: a sticker shop cannot print
            // a 24×36, so the history jobs must fit each matrix, not one shape.
            $tier = $shop->services[0]->tiers->first();
            $edge = $tier->max_width !== null ? (float) $tier->max_width : (float) $tier->min_width + 1;

            $jobs[] = ['shop' => $index, 'service' => 0, 'w' => $edge, 'h' => $edge, 'qty' => 2, 'status' => 'collected', 'user' => $jomar, 'due' => '-21 days', 'options' => []];
            $jobs[] = ['shop' => $index, 'service' => 0, 'w' => $edge, 'h' => $edge, 'qty' => 5, 'status' => 'collected', 'user' => $dennis, 'due' => '-35 days', 'options' => []];
        }

        foreach ($jobs as $data) {
            $shop = $shops[$data['shop']];
            $service = $shop->services[$data['service']];

            $spec = [
                'service_id' => $service->id,
                'width_in' => $data['w'],
                'height_in' => $data['h'],
                'qty' => $data['qty'],
                'options' => collect($data['options'])->map(fn ($pair) => [
                    'id' => $service->options[$pair[0]]->id,
                    'qty' => $pair[1],
                ])->all(),
            ];

            $quote = $calculator->quote($shop, $spec);

            // "today" is stamped at the end of the day, not at 17:00: a seeded
            // database opened after five in the afternoon must not show its own
            // "due today" work as already overdue.
            $dueAt = $data['due'] === 'today'
                ? Carbon::today()->endOfDay()
                : Carbon::now()->modify($data['due'])->setTime(17, 0);

            // Placed two days before the work is due, but never in the future.
            // The dashboard measures "waiting" from these stamps, so they have
            // to be plausible rather than "everything happened a second ago".
            //
            // Two days is deliberately *not* the shop's turnaround. `due_at` is
            // the date this job is wanted, and a customer may ask for a date
            // later than the shop's typical turnaround — so the two numbers are
            // allowed to differ, and the demo needs the spread. It is not
            // allowed to differ by a week for a shop advertising 24 hours,
            // though, which is what the authored offsets produced for the
            // short-turnaround shops; see the note on the job page.
            $placedAt = $dueAt->copy()->subDays(2)->min(Carbon::now()->subDays(2));
            $touchedAt = $placedAt->copy()->addHours(6)->min(Carbon::now());

            $job = Job::create([
                'shop_id' => $shop->id,
                'user_id' => $data['user']->id,
                'service_id' => $service->id,
                'status' => $data['status'],
                'spec' => $spec,
                'subtotal' => $quote['subtotal'],
                'discount' => $quote['discount'],
                'total' => $quote['total'],
                'matrix_version' => $quote['matrix_version'],
                'pickup_city' => $shop->city,
                'due_at' => $dueAt,
                'created_at' => $placedAt,
                'updated_at' => $touchedAt,
                // Stamped by JobWorkflow in real use; set here so the dashboard's
                // "collected" figures are not all zero in the seeded database.
                // Bounded by now: a job due today at 16:00 was otherwise stamped
                // as collected in the future whenever the seed ran in the morning.
                'collected_at' => $data['status'] === 'collected'
                    ? $dueAt->copy()->setTime(16, 0)->min(Carbon::now())
                    : null,
            ]);

            foreach ($quote['lines'] as $line) {
                $job->lines()->create($line);
            }

            // A quoted job always has a payment beside it in real use: checkout
            // creates both in one transaction, the job `quoted` and the payment
            // `pending`. Seeding one keeps the dashboard's "Unpaid" row visible,
            // which is the whole point of that row existing.
            if ($data['status'] === 'quoted') {
                $job->payment()->create([
                    'provider' => 'paymongo',
                    'intent_id' => 'pi_test_'.$job->reference,
                    'amount' => $quote['total'],
                    'processing_fee' => Payment::estimateFee($quote['total']),
                    'commission' => (int) round($quote['total'] * $shop->commission_rate / 10000),
                    'status' => 'pending',
                ]);
            }

            if (in_array($data['status'], ['awaiting_proof', 'approved', 'in_production', 'ready', 'collected'], true)) {
                $job->proofs()->create([
                    'version' => 1,
                    'file_path' => 'proofs/sample.pdf',
                    'status' => $data['status'] === 'awaiting_proof' ? 'sent' : 'approved',
                    'approved_at' => $data['status'] === 'awaiting_proof' ? null : Carbon::now()->subDay(),
                    // Backdated to when the job was placed: a proof that appears
                    // to have been sent this second makes the dashboard's
                    // "customer hasn't approved" rule impossible to see.
                    'created_at' => $placedAt,
                    'updated_at' => $placedAt,
                ]);
            }

            if (in_array($data['status'], ['approved', 'in_production', 'ready', 'collected'], true)) {
                // Paid when the proof was approved, two days before the pickup
                // date — unless the row names a day, which the job paid today
                // does so "Revenue today" has a figure on a fresh clone.
                // Anything older than ten days has already been released to the
                // shop, so the ledger shows both settled and open rows.
                $paidAt = isset($data['paid'])
                    ? Carbon::now()->modify($data['paid'])
                    : $job->due_at->copy()->subDays(2);

                if ($paidAt->isFuture()) {
                    $paidAt = Carbon::now()->subDay();
                }

                $released = $paidAt->lt(Carbon::now()->subDays(10));

                $job->payment()->create([
                    'provider' => 'paymongo',
                    'intent_id' => 'pi_test_'.$job->reference,
                    'amount' => $quote['total'],
                    'processing_fee' => Payment::estimateFee($quote['total']),
                    'commission' => (int) round($quote['total'] * $shop->commission_rate / 10000),
                    'status' => 'paid',
                    'paid_at' => $paidAt,
                    'payout_status' => $released ? 'paid' : 'pending',
                    'payout_date' => $released ? $paidAt->copy()->addDays(5)->toDateString() : null,
                ]);
            }
        }
    }

    private function content(): void
    {
        $sections = [
            ['key' => 'hero', 'label' => 'Homepage hero', 'sort' => 1, 'content' => [
                'eyebrow' => 'Print marketplace · Metro Manila',
                'headline' => "Custom printing,\npriced in seconds.",
                'subheadline' => 'Find a verified print shop, set your size, material and quantity — and see the exact price before you order. No group chats. No “pm sent.” No waiting.',
                'primary_label' => 'Find a print shop',
                'primary_link' => '/shops',
            ]],
            // The design has no announcement bar, so the section ships as a draft:
            // it exists in the CMS to be switched on, but the homepage is clean.
            ['key' => 'banner', 'label' => 'Announcement banner', 'sort' => 2, 'status' => 'draft', 'content' => [
                'text' => 'Free proof on every job over ₱1,500 — approve before we print.',
                'link_label' => 'How proofs work',
                'link' => '/shops',
            ]],
            ['key' => 'featured_shops', 'label' => 'Featured shops', 'sort' => 3, 'content' => [
                'title' => 'Shops near you',
                'subtitle' => 'Verified, priced and rated by customers who actually picked up.',
            ]],
            ['key' => 'testimonials', 'label' => 'Testimonials', 'sort' => 4, 'content' => [
                'title' => 'People who stopped sending “pm sent”.',
                // The third card is the shop-owner voice; the design rings it so the
                // section speaks to both sides of the marketplace, not just buyers.
                'items' => [
                    ['quote' => 'I ordered 3 tarpaulins at 11 pm and had a price in ten seconds. That used to take two days of asking around.', 'name' => 'Jomar M.', 'role' => 'Small business owner · QC'],
                    ['quote' => 'The proof approval step saved me. They caught a low-res logo before printing 200 flyers.', 'name' => 'Angela R.', 'role' => 'Marketing associate'],
                    ['quote' => 'As a shop owner, the price matrix is the whole thing. I set it once and it just answers for me.', 'name' => 'Kevin C.', 'role' => 'Owner, Inkwell Press', 'highlight' => true],
                ],
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
                'blurb' => 'The print marketplace for tarpaulins, stickers, flyers and everything else you need on paper.',
            ]],
        ];

        foreach ($sections as $section) {
            PageSection::create($section + ['status' => 'published']);
        }

        SiteSetting::create(['key' => 'support_email', 'value' => 'hello@tarpalink.ph']);
        SiteSetting::create(['key' => 'commission_percent', 'value' => '5']);
        SiteSetting::create(['key' => 'platform_name', 'value' => 'TarpaLink']);
    }
}
