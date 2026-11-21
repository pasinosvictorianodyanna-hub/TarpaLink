<?php

namespace Tests\Feature;

use App\Models\PageSection;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The landing page against the approved design.
 *
 * Each test here stands for one thing the design does that the page previously
 * did not: four cards in a four-column row, an accent stroke on the payoff word,
 * one differentiating badge per shop, a distance rather than a city, and the
 * shop-owner review called out from the two customer reviews.
 */
class LandingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_the_featured_row_fills_all_four_columns(): void
    {
        $response = $this->get('/')->assertOk();

        $this->assertCount(4, $response->viewData('featured'));

        // Three cards in a four-column grid leaves a hole the width of a shop.
        $this->assertSame(4, substr_count($response->getContent(), '<article class="shop">'));
    }

    public function test_the_last_word_of_the_headline_carries_the_accent_stroke(): void
    {
        PageSection::where('key', 'hero')->update(['content' => [
            'eyebrow' => 'Print marketplace · Metro Manila',
            'headline' => "Custom printing,\npriced in seconds.",
            'subheadline' => 'Find a verified print shop.',
        ]]);

        $this->get('/')
            ->assertOk()
            ->assertSee('mark-under">seconds.', false);
    }

    public function test_each_featured_shop_shows_the_badge_that_sets_it_apart(): void
    {
        $featured = Shop::active()
            ->with('quantityDiscounts')
            ->orderByDesc('rating')
            ->take(4)
            ->get();

        $labels = $featured->map(fn (Shop $shop) => $shop->badges()[0]['label'] ?? null);

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
        $content = $this->get('/')->assertOk()->getContent();

        // Exactly one ringed card — the supply-side voice, not a "recommended" one.
        $this->assertSame(1, substr_count($content, 'class="quote hl"'));
        $this->assertSame(2, substr_count($content, 'class="quote"'));
    }

    public function test_the_announcement_bar_is_draft_so_the_homepage_matches_the_design(): void
    {
        $this->assertSame('draft', PageSection::where('key', 'banner')->value('status'));

        $this->get('/')->assertOk()->assertDontSee('Free proof on every job over');
    }

    public function test_every_nav_link_lands_on_an_element_that_exists(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        foreach (['how', 'pricing', 'owners'] as $anchor) {
            $this->assertStringContainsString('id="'.$anchor.'"', $html, "Missing #{$anchor} target.");
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
