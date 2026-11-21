<?php

namespace Database\Seeders;

use App\Models\Job;
use App\Models\PageSection;
use App\Models\PriceOption;
use App\Models\PriceTier;
use App\Models\QuantityDiscount;
use App\Models\Service;
use App\Models\Shop;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\PriceCalculator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $shops = $this->shops();

        $this->people($shops);
        $this->content();
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
                'address' => 'Unit 4B, Chino Roces Ave', 'accent' => 'magenta',
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
                'address' => '12 Meralco Ave, Ortigas', 'accent' => 'cyan',
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
                'address' => '5 Samson Rd, Monumento', 'accent' => 'ink',
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
        ];

        $created = [];

        foreach ($blueprint as $index => $data) {
            $shop = Shop::create([
                'name' => $data['name'],
                'slug' => $data['slug'],
                'city' => $data['city'],
                'address' => $data['address'],
                'phone' => $data['phone'],
                'description' => $data['description'],
                'accent' => $data['accent'],
                'status' => 'active',
                'plan' => $data['plan'],
                'commission_rate' => $data['rate'],
                'rating' => $data['rating'],
                'jobs_done' => $data['jobs_done'],
                'lat' => 14.55 + ($index * 0.021),
                'lng' => 120.98 + ($index * 0.019),
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
                'due_at' => Carbon::now()->modify($data['due'])->setTime(17, 0),
            ]);

            foreach ($quote['lines'] as $line) {
                $job->lines()->create($line);
            }

            if (in_array($data['status'], ['awaiting_proof', 'approved', 'in_production', 'ready', 'collected'], true)) {
                $job->proofs()->create([
                    'version' => 1,
                    'file_path' => 'proofs/sample.pdf',
                    'status' => $data['status'] === 'awaiting_proof' ? 'sent' : 'approved',
                    'approved_at' => $data['status'] === 'awaiting_proof' ? null : Carbon::now()->subDay(),
                ]);
            }

            if (in_array($data['status'], ['approved', 'in_production', 'ready', 'collected'], true)) {
                $job->payment()->create([
                    'provider' => 'paymongo',
                    'intent_id' => 'pi_test_'.$job->reference,
                    'amount' => $quote['total'],
                    'processing_fee' => (int) round($quote['total'] * 0.025) + 1500,
                    'commission' => (int) round($quote['total'] * $shop->commission_rate / 10000),
                    'status' => 'paid',
                    'paid_at' => Carbon::now()->subDays(2),
                ]);
            }
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
                'items' => [
                    ['quote' => 'I used to send a message and wait half a day for a price. Now I know before I leave the house.', 'name' => 'Marisol Reyes', 'role' => 'Events coordinator, QC'],
                    ['quote' => 'The proof approval saved me a reprint. Caught a wrong logo before it went on 200 shirts.', 'name' => 'Dennis Lim', 'role' => 'Bar owner, Makati'],
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
                'blurb' => 'TarpaLink is a print marketplace for the Philippines. Exact prices, approved proofs, pickup at the counter.',
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
