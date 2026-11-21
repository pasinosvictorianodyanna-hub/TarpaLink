<?php

namespace Database\Seeders;

use App\Models\PageSection;
use Illuminate\Database\Seeder;

/**
 * The eight pages the footer links to.
 *
 * Kept in its own seeder rather than inlined in DatabaseSeeder for one practical
 * reason: it is idempotent, so `db:seed --class=PageContentSeeder` can refresh
 * the footer copy on an existing database without touching jobs, payments or
 * shops. A `create()`-only seeder cannot be re-run at all.
 *
 * Slugs must match PageController::SLUGS, or the /{slug} route will not answer.
 */
class PageContentSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->pages() as $slug => $page) {
            PageSection::updateOrCreate(
                ['key' => 'page.'.$slug],
                [
                    'label' => $page['title'],
                    'sort' => 100,
                    'status' => 'published',
                    'content' => [
                        'eyebrow' => $page['eyebrow'],
                        'title' => $page['title'],
                        'lede' => $page['lede'],
                        'updated' => $page['updated'],
                        'blocks' => $page['blocks'],
                    ],
                ],
            );
        }
    }

    /** @return array<string, array{eyebrow:string,title:string,lede:string,updated:string,blocks:array}> */
    private function pages(): array
    {
        return [
            'about' => [
                'eyebrow' => 'Company',
                'title' => 'About TarpaLink',
                'updated' => 'November 2026',
                'lede' => 'TarpaLink is a print marketplace for Metro Manila. You describe the job, every shop quotes it from its own published price matrix, and you order without a single “pm sent”.',
                'blocks' => [
                    ['h' => 'Why we built it', 'p' => [
                        'Ordering printing usually starts in a chat thread. You send your size, someone asks for the material, you wait, someone else quotes a number with no breakdown, and by the time you agree on a price the artwork has been re-sent three times.',
                        'TarpaLink moves that negotiation into software. Shops publish the rates they already use — per square foot, per piece, per material tier — and the platform turns those rates into an itemised total the moment you change a spec. The number you see is the number you pay.',
                    ]],
                    ['h' => 'What we are not', 'p' => [
                        'We are not a print shop and we do not own a press. Every job is produced by an independent shop, and that shop is named on your job order, on your receipt and on the door you collect from.',
                        'We are also not an e-commerce catalogue. Nothing here is a pre-made product with a fixed photo and a fixed price. A tarpaulin is 36 × 24 inches or it is 96 × 48; the price is a function of the specs you choose, which is why the quote comes before the cart rather than after it.',
                    ]],
                    ['h' => 'Where we are', 'p' => [
                        'The shops listed today are in Quezon City, Makati, Pasig, Manila and Caloocan. Pickup is at the shop counter; delivery is being rolled out shop by shop.',
                    ]],
                ],
            ],

            'contact' => [
                'eyebrow' => 'Company',
                'title' => 'Contact us',
                'updated' => 'November 2026',
                'lede' => 'One inbox, answered by a person. We reply within one business day, Metro Manila time.',
                'blocks' => [
                    ['h' => 'For customers', 'p' => [
                        'If a job is late, damaged or not what you approved, message us with the job reference — the code on your job page, like TL-8F2A9C. That reference is what lets us pull the order, the proof you approved and the payment in one look.',
                        'Questions about a quote should go to the shop first. They set the rates, and they can tell you why a material tier costs what it costs.',
                    ]],
                    ['h' => 'For shop owners', 'p' => [
                        'Billing, payout ledger and production questions are handled from the shop console under Reports. If something there does not reconcile, send us the payout date and the job reference and we will trace it against the payment record.',
                    ]],
                    ['h' => 'Hours', 'p' => [
                        'Monday to Friday, 9:00 to 18:00. Weekends are monitored for jobs that are already in production.',
                    ]],
                ],
            ],

            'privacy' => [
                'eyebrow' => 'Legal',
                'title' => 'Privacy policy',
                'updated' => 'November 2026',
                'lede' => 'What we collect, why we need it, and how long we keep it. Short version: the data required to price, print and hand over a job.',
                'blocks' => [
                    ['h' => 'What we collect', 'p' => [
                        'Account details: your name, email address and a hashed password. We never store the password itself.',
                        'Job details: the shop, service, dimensions, quantity, finishing options, the artwork file you upload, and the contact name and phone number for the order. If you choose delivery, the pickup person and address you enter.',
                        'Payment records: the amount, the payment method, the PayMongo reference and the timestamp. Card and wallet details are handled by PayMongo — they never reach our servers.',
                    ]],
                    ['h' => 'Why we need it', 'p' => [
                        'Pricing and production need the specs. Handover needs a name and a phone number so the shop can reach you about a proof. Payment needs a reference so a charge can be matched to an order. That is the whole purpose; we do not sell data and we do not run third-party advertising trackers on this site.',
                    ]],
                    ['h' => 'Who can see a job', 'p' => [
                        'You, the shop producing it, and platform administrators who handle disputes and payouts. Other customers cannot see your jobs, and one shop cannot see another shop’s orders. Access is enforced on the server for every request, not just hidden in the interface.',
                    ]],
                    ['h' => 'How long we keep it', 'p' => [
                        'Job and payment records are kept for as long as your account is open, because a receipt has to remain retrievable. Artwork files are kept with the job so a reprint is possible. You can ask us to close your account and delete your artwork; the payment records required for accounting are retained.',
                    ]],
                    ['h' => 'Your choices', 'p' => [
                        'You can correct your name, phone and email from your profile at any time. To request an export or a deletion, email us from the address on the account so we can verify it is you.',
                    ]],
                ],
            ],

            'terms' => [
                'eyebrow' => 'Legal',
                'title' => 'Terms of service',
                'updated' => 'November 2026',
                'lede' => 'The rules that apply when you order through TarpaLink, and the ones that apply when you sell through it.',
                'blocks' => [
                    ['h' => 'Accounts', 'p' => [
                        'You need an account to place an order. You are responsible for what happens under it, so keep your password to yourself and tell us if you think someone else has it.',
                        'Shop accounts are reviewed before the console opens. A shop must be a real printing business able to produce the services it lists.',
                    ]],
                    ['h' => 'Prices and quotes', 'p' => [
                        'Prices are set by each shop, not by TarpaLink. A quote is calculated from the shop’s published matrix and the specifications you entered. If a specification is impossible or mispriced because of a data error, the shop may cancel the job and refund it in full rather than produce it at a loss.',
                        'The total shown at checkout is the total charged. There is no separate platform fee added on top of the itemised breakdown.',
                    ]],
                    ['h' => 'Artwork and proofs', 'p' => [
                        'You are responsible for having the right to print the artwork you upload. Do not upload anything you do not own or have permission to reproduce.',
                        'Every job passes through a proof before it goes to press. The proof is your last chance to catch a low-resolution logo, a typo or a wrong size. Once you approve it, the job is locked and the artwork is printed as approved.',
                    ]],
                    ['h' => 'Payment', 'p' => [
                        'You can pay online through PayMongo with GCash, Maya or a card, or pay cash at the counter on pickup. The price is the same either way. Online payments are captured when the order is placed; cash is collected at handover.',
                    ]],
                    ['h' => 'Cancellation and refunds', 'p' => [
                        'A job can be cancelled for a full refund any time before you approve the proof, because nothing has been printed yet. After approval the material is committed, so cancellation is at the shop’s discretion and a refund may be partial.',
                        'If the print does not match the proof you approved, tell us within seven days of pickup with photos and the job reference. We will arrange a reprint or a refund with the shop.',
                    ]],
                    ['h' => 'For shop owners', 'p' => [
                        'Keep your price matrix accurate — it is what customers are quoted, and a quote you cannot honour is your liability. Keep proof turnaround inside the window shown on the job, and mark jobs collected so the payout ledger stays truthful.',
                        'Commission is charged per completed job at the rate of your current plan. Payouts are released through the platform ledger and recorded against each payment.',
                    ]],
                    ['h' => 'Liability', 'p' => [
                        'TarpaLink provides the marketplace, the pricing engine and the job record. The physical print is the shop’s work. Our liability for any job is limited to the amount you paid for it.',
                    ]],
                    ['h' => 'Changes', 'p' => [
                        'We will update these terms as the product changes and change the date at the top when we do. Continuing to use the service after a change means you accept the updated terms.',
                    ]],
                ],
            ],

            'help' => [
                'eyebrow' => 'Support',
                'title' => 'Help center',
                'updated' => 'November 2026',
                'lede' => 'The five things people ask about most: quoting, proofs, payment, pickup and what to do when something is wrong.',
                'blocks' => [
                    ['h' => 'Getting a price', 'p' => [
                        'Open a shop, pick a service, then set the width, height and quantity. The total updates as you type because it is calculated on our server against the shop’s matrix — the figure in the builder is the figure at checkout.',
                        'Quantity discounts apply automatically at the thresholds the shop publishes. You do not have to ask for them.',
                    ]],
                    ['h' => 'Approving a proof', 'p' => [
                        'After you order, the shop uploads a proof and the job moves to “Proof ready”. Open the job, look at the proof at full size, and approve it — or send it back with a note. The job does not go to press until you approve.',
                        'Approve carefully. Once approved, the artwork is locked and cannot be changed.',
                    ]],
                    ['h' => 'Tracking a job', 'p' => [
                        'My jobs lists everything you have ordered with a status rail: placed, paid, proof, in production, ready, collected. The same rail is on the job page, along with the shop’s counter address and the time your print will be ready.',
                    ]],
                    ['h' => 'Paying', 'p' => [
                        'GCash, Maya and cards are handled by PayMongo at checkout. Cash is paid at the counter on pickup, at the same price. Your receipt is on the job page and can be printed or saved as a PDF from the browser.',
                    ]],
                    ['h' => 'Something went wrong', 'p' => [
                        'Wrong size, wrong material, a print that does not match the proof — email us with the job reference and photos within seven days of pickup. We will sort it out with the shop and arrange a reprint or a refund.',
                    ]],
                ],
            ],

            'pricing' => [
                'eyebrow' => 'Shop owners',
                'title' => 'Pricing and fees',
                'updated' => 'November 2026',
                'lede' => 'No setup fee, no listing fee, no charge for the quotes that do not convert. You pay commission on completed jobs only.',
                'blocks' => [
                    ['h' => 'The three plans', 'p' => [
                        'Starter — ₱0 a month, 5% commission per completed job. Storefront listing, price matrix, proof approval.',
                        'Growth — ₱599 a month, commission drops to 3%. Adds the payout ledger and report exports.',
                        'Pro — ₱1,499 a month, commission drops to 1.5%. Adds priority placement in the directory and multi-staff access to the console.',
                    ]],
                    ['h' => 'What commission covers', 'p' => [
                        'The storefront, the pricing engine, proof handling, payment collection and the job record. You keep the rest; the commission is taken from the job total at settlement, not billed separately.',
                    ]],
                    ['h' => 'When you get paid', 'p' => [
                        'Each completed job creates a payout entry. Payouts are released from the platform ledger after the job is collected, and every release is dated and matched to the payment it came from — you can see the whole history under Reports in the console.',
                    ]],
                    ['h' => 'Changing plan', 'p' => [
                        'Move up or down at any time. The commission rate that applies is the one on your plan when the job is completed, so a downgrade never re-prices work you have already delivered.',
                    ]],
                ],
            ],

            'partners' => [
                'eyebrow' => 'Shop owners',
                'title' => 'Partner guidelines',
                'updated' => 'November 2026',
                'lede' => 'What we expect from a shop on TarpaLink, and what a shop can expect from us.',
                'blocks' => [
                    ['h' => 'To be listed', 'p' => [
                        'You need a real, operating print business with a counter customers can collect from, at least one service with a complete price matrix, and someone who answers the phone number on the account.',
                    ]],
                    ['h' => 'Keep the matrix honest', 'p' => [
                        'Your matrix is the quote. If a tier, a finishing fee or a minimum charge is out of date, customers are being quoted a number you cannot honour. Update it before you need to, not after a job goes wrong.',
                        'Do not publish a low headline rate and recover it with undisclosed add-ons at handover. The breakdown the customer sees at checkout is the breakdown they are entitled to.',
                    ]],
                    ['h' => 'Proofs and production', 'p' => [
                        'Upload a proof for every job, and upload it inside the window shown on the job. If the artwork will not print well, say so in the proof note before the customer approves — catching a low-resolution logo is the point of the step.',
                        'Print what was approved. A substitution of material or size without the customer’s agreement is a failed job, not a favour.',
                    ]],
                    ['h' => 'Payouts and records', 'p' => [
                        'Mark jobs collected when the customer picks up. The payout ledger and your reports are built from that record; a job left open is a payout that never releases and a customer who keeps getting reminders.',
                    ]],
                    ['h' => 'Conduct', 'p' => [
                        'Do not move a TarpaLink customer off-platform to avoid commission on a job that started here. Do not use customer artwork for your own portfolio without permission. Repeated cancellations, no-shows or unresolved disputes will get a listing suspended.',
                    ]],
                ],
            ],

            'open-a-shop' => [
                'eyebrow' => 'Shop owners',
                'title' => 'Open a shop on TarpaLink',
                'updated' => 'November 2026',
                'lede' => 'Publish your price matrix once and let the platform answer the “how much?” messages for you.',
                'blocks' => [
                    ['h' => 'What you need before you start', 'p' => [
                        'Your rates per service — per square foot for large format, per piece for stickers and apparel — with the material tiers you actually sell and any minimum charge.',
                        'Your finishing fees (eyelets, hemming, lamination, cutting), your quantity discount thresholds, and how long a typical job takes to produce.',
                    ]],
                    ['h' => 'How onboarding works', 'p' => [
                        'Create an account, then send us your business name, counter address and the services you want to list. We review the application, and once it is approved the shop console opens.',
                        'Inside the console you build the price matrix, set your proof window and turn on rush orders if you can take them. Your storefront goes live the moment the matrix is complete — there is no separate publishing step.',
                    ]],
                    ['h' => 'What it costs', 'p' => [
                        'Nothing to list and nothing to set up. Starter is free and charges 5% of each completed job; Growth and Pro lower that rate for a monthly fee. See Pricing and fees for the full comparison.',
                    ]],
                    ['h' => 'What you get', 'p' => [
                        'A storefront with your own matrix and badges, jobs that arrive priced and paid with the artwork attached, a proof step that protects you from reprints, a production board for the floor, and a payout ledger that reconciles against each payment.',
                    ]],
                ],
            ],
        ];
    }
}
