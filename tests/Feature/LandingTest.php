<?php

namespace Tests\Feature;

use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The landing page against the approved design.
 *
 * The page is a Vue component now, so the server no longer emits the markup the
 * old version of this file counted. What the server still decides — and what
 * these tests therefore hold it to — is the props contract: how many cards, how
 * the headline splits, which badge, which review is ringed, whether the banner
 * is live. The component only lays those values out.
 *
 * That is the same split the page itself makes, so a test that breaks here means
 * the server changed its mind about content, not that the CSS moved.
 */
class LandingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /**
     * The props the controller hands the component.
     *
     * @return array<string, mixed>
     */
    private function props(): array
    {
        return $this->get('/')->assertOk()->viewData('props');
    }

    public function test_the_page_mounts_as_a_vue_component_with_serialisable_props(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('data-component="home"', $html);

        // A prop that cannot survive JSON is a prop the browser never receives.
        preg_match('/data-props="([^"]*)"/', $html, $match);
        $this->assertNotEmpty($match, 'The mount host is missing data-props.');

        $decoded = json_decode(html_entity_decode($match[1], ENT_QUOTES), true);
        $this->assertIsArray($decoded, 'data-props is not valid JSON.');
        $this->assertArrayHasKey('hero', $decoded);
        $this->assertArrayHasKey('featured', $decoded);
    }

    public function test_the_featured_row_fills_all_four_columns(): void
    {
        // Three cards in a four-column grid leaves a hole the width of a shop.
        $this->assertCount(4, $this->props()['featured']);
    }

    public function test_every_card_carries_the_strings_it_renders(): void
    {
        foreach ($this->props()['featured'] as $card) {
            foreach (['name', 'url', 'cover', 'rating', 'jobsDone', 'distance', 'price', 'unit'] as $key) {
                $this->assertArrayHasKey($key, $card, "A shop card is missing {$key}.");
            }

            // The card prints "from ₱45/sq ft", so the peso sign and the unit
            // belong to the server — the component must not format money.
            $this->assertStringStartsWith('₱', $card['price']);
            $this->assertMatchesRegularExpression('/^\d+\.\d km$/', $card['distance']);
        }
    }

    public function test_the_last_word_of_the_headline_carries_the_accent_stroke(): void
    {
        $this->fakeCms(['hero' => [
            'eyebrow' => 'Print marketplace · Metro Manila',
            'headline' => "Custom printing,\npriced in seconds.",
            'subheadline' => 'Find a verified print shop.',
        ]]);

        $hero = $this->props()['hero'];

        // The accent stroke sits under `tail`; everything before it is plain.
        $this->assertSame(['Custom printing,'], $hero['lines']);
        $this->assertSame('priced in', $hero['head']);
        $this->assertSame('seconds.', $hero['tail']);
    }

    public function test_the_accent_follows_the_last_word_when_the_headline_is_rewritten(): void
    {
        $this->fakeCms(['hero' => [
            'eyebrow' => 'Any',
            'headline' => 'Everything you need to get printed',
            'subheadline' => 'Any',
        ]]);

        $hero = $this->props()['hero'];

        // One line, so nothing is left for `lines` and the split moves up a level.
        $this->assertSame([], $hero['lines']);
        $this->assertSame('Everything you need to get', $hero['head']);
        $this->assertSame('printed', $hero['tail']);
    }

    public function test_each_featured_shop_shows_the_badge_that_sets_it_apart(): void
    {
        $labels = collect($this->props()['featured'])
            ->map(fn (array $card) => $card['badge']['label'] ?? null);

        // The card shows one badge, so the first one has to differentiate. If two
        // shops share it the row reads as decoration, which is what the design
        // avoids by giving every card a different claim.
        $this->assertSame($labels->unique()->count(), 4, 'Badges repeat: '.$labels->implode(', '));
    }

    public function test_a_shop_card_shows_distance_and_falls_back_to_city_without_coordinates(): void
    {
        $shop = Shop::where('slug', 'inkwell-press')->firstOrFail();

        $this->assertMatchesRegularExpression('/^\d+\.\d km$/', $shop->distanceLabel());

        $unlocated = new Shop(['city' => 'Pasay']);
        $this->assertSame('Pasay', $unlocated->distanceLabel());
    }

    public function test_the_shop_owner_review_is_ringed_and_the_others_are_not(): void
    {
        $items = collect($this->props()['testimonials']['items']);

        // Exactly one ringed card — the supply-side voice, not a "recommended" one.
        $this->assertSame(1, $items->where('highlight', true)->count());
        $this->assertSame(2, $items->where('highlight', false)->count());
    }

    public function test_the_announcement_bar_is_absent_while_the_cms_holds_it_back(): void
    {
        // The banner ships as a draft, and the CMS does not serve drafts — so the
        // engine has nothing to render. Null, not an empty string: the component
        // omits the bar entirely.
        $this->assertNull($this->props()['banner']);
        $this->get('/')->assertOk()->assertDontSee('Free proof on every job over');
    }

    public function test_the_announcement_bar_appears_once_the_cms_publishes_it(): void
    {
        $this->fakeCms([
            'banner' => [
                'text' => 'Free proof on every job over ₱1,500.',
                'link' => '/help',
                'link_label' => 'How proofs work',
            ],
        ]);

        $banner = $this->props()['banner'];

        $this->assertIsArray($banner);
        $this->assertStringContainsString('Free proof on every job over', $banner['text']);
    }

    public function test_the_storefront_still_renders_when_the_cms_is_unreachable(): void
    {
        // The two systems fail independently now. Losing the CMS must cost the
        // page its edited copy, not its existence.
        $this->cmsIsDown();

        $props = $this->props();

        // Falls back to the headline compiled into the controller.
        $this->assertSame('seconds.', $props['hero']['tail']);
        $this->assertNull($props['banner']);
        $this->assertNull($props['testimonials']);
        $this->assertSame([], $props['faq'] ?? []);
    }

    /**
     * The nav is Blade and the page is Vue, so the only thing they share is the
     * anchor id. A rename inside the component silently breaks the nav, which is
     * exactly the kind of dead link the footer test already guards against.
     */
    public function test_every_nav_anchor_has_a_target_in_the_page_component(): void
    {
        $page = file_get_contents(resource_path('js/pages/Home.vue'));

        foreach (['how', 'pricing', 'owners'] as $anchor) {
            $this->assertStringContainsString(
                'id="'.$anchor.'"',
                $page,
                "The nav links to #{$anchor} but the page has no such id.",
            );
        }
    }

    public function test_the_public_nav_points_at_marketing_sections_not_the_directory(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        // The nav block only — the hero CTA and the "Get a quote" button still
        // lead into /shops, which is where a visitor actually enters.
        $nav = substr($html, strpos($html, 'mk-links'));
        $nav = substr($nav, 0, strpos($nav, 'mk-actions'));

        $this->assertStringNotContainsString(
            route('shops.index'),
            $nav,
            'The directory belongs in the signed-in app bar, not the marketing nav.',
        );

        foreach (['#pricing', '#how', '#owners'] as $anchor) {
            $this->assertStringContainsString($anchor, $nav, "Nav is missing {$anchor}.");
        }
    }
}
