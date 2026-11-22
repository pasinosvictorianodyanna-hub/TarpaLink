<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Services\CmsClient;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function __construct(private readonly CmsClient $cms) {}

    public function index(): View
    {
        // Four, because the row is a four-column grid: three cards left a hole the
        // width of a shop on the right-hand edge.
        $featured = Shop::active()
            ->with(['services.tiers', 'quantityDiscounts'])
            ->orderByDesc('rating')
            ->take(4)
            ->get();

        $hero = $this->cms->section('hero', [
            'eyebrow' => 'Print marketplace · Metro Manila',
            'headline' => "Custom printing,\npriced in seconds.",
            'subheadline' => 'Find a verified print shop, set your size, material and quantity — and see the exact price before you order. No group chats. No “pm sent.” No waiting.',
            'primary_label' => 'Find a print shop',
        ]);

        return view('home', [
            'props' => [
                'hero' => $this->hero($hero),
                'banner' => $this->banner(),
                'featured' => $featured->map(fn (Shop $shop) => $this->card($shop))->all(),
                'shopCount' => Shop::active()->count(),
                'testimonials' => $this->section('testimonials'),
                'faq' => $this->section('faq'),
                'routes' => [
                    'shops' => route('shops.index'),
                    'openShop' => route('page', 'open-a-shop'),
                ],
            ],
        ]);
    }

    /**
     * The headline is split here, not in the component.
     *
     * The design puts a red brush stroke under the payoff word — always the last
     * word — so an editor can rewrite the headline in the CMS and the accent
     * still lands on the right word. That is a rule about the content, so it is
     * decided on the server where it can be tested.
     *
     * @param  array<string, mixed>  $hero
     * @return array<string, mixed>
     */
    private function hero(array $hero): array
    {
        $lines = array_values(array_filter(
            array_map('trim', explode("\n", (string) ($hero['headline'] ?? ''))),
            fn (string $line) => $line !== '',
        ));

        $lastLine = (string) array_pop($lines);
        $words = preg_split('/\s+/', $lastLine, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $tail = (string) array_pop($words);

        return [
            'eyebrow' => $hero['eyebrow'] ?? '',
            'lines' => $lines,
            'head' => implode(' ', $words),
            'tail' => $tail,
            'subheadline' => $hero['subheadline'] ?? '',
        ];
    }

    /**
     * One shop card, flattened to the strings the card actually renders.
     *
     * The price, the badge and the distance are all derived — cheapest tier,
     * rarest badge, haversine from the origin — so they are resolved here rather
     * than shipped to the browser as rules the client could get wrong.
     *
     * @return array<string, mixed>
     */
    private function card(Shop $shop): array
    {
        $badge = $shop->badges()[0] ?? null;

        return [
            'name' => $shop->name,
            'url' => route('shops.show', $shop),
            'cover' => $shop->coverGradient(),
            'rating' => number_format($shop->rating, 1),
            'jobsDone' => $shop->jobs_done,
            'distance' => $shop->distanceLabel(),
            'price' => '₱'.number_format($shop->cheapestRate() / 100),
            'unit' => $shop->cheapestUnitLabel(),
            'badge' => $badge === null ? null : ['label' => $badge['label'], 'tone' => $badge['tone']],
        ];
    }

    /** The announcement bar, or null when it is unpublished. */
    private function banner(): ?array
    {
        $banner = $this->section('banner');

        if ($banner === null || empty($banner['text'])) {
            return null;
        }

        return [
            'text' => $banner['text'],
            'link' => $banner['link'] ?? null,
            'linkLabel' => $banner['link_label'] ?? null,
        ];
    }

    /**
     * A section's content, or null when it is missing or not published.
     *
     * Null is meaningful: the component omits the block entirely rather than
     * rendering an empty shell, so deleting a section hides it.
     */
    private function section(string $key): ?array
    {
        return $this->cms->find($key);
    }
}
