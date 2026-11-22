<?php

namespace Tests\Feature;

use App\Http\Controllers\PageController;
use App\Models\PageSection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The footer used to be twelve `href="#"` anchors — every one of them a dead end.
 *
 * These tests hold the line on the fix: no dead anchors, every destination
 * resolves, and the `/{slug}` catch-all cannot swallow a real route.
 */
class FooterLinksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_the_footer_has_no_dead_anchors(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $footer = substr($html, strpos($html, 'foot-grid'));

        $this->assertStringNotContainsString(
            'href="#"',
            $footer,
            'A footer link still points nowhere.',
        );
    }

    public function test_every_footer_destination_is_rendered(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $expected = [
            route('shops.index'),
            route('home').'#pricing',
            route('account'),
            route('page', 'help'),
            route('page', 'open-a-shop'),
            route('page', 'pricing'),
            route('page', 'partners'),
            route('page', 'about'),
            route('page', 'contact'),
            route('page', 'privacy'),
            route('page', 'terms'),
        ];

        foreach ($expected as $href) {
            $this->assertStringContainsString('href="'.$href.'"', $html, "Footer is missing {$href}.");
        }

        // The console is now a named route of its own. The Filament panel still
        // owns the screens that have not been ported, but the front door is the
        // console, not the panel.
        $this->assertStringContainsString('href="'.route('console.dashboard').'"', $html);
    }

    public function test_each_footer_page_renders_its_own_copy(): void
    {
        foreach (PageController::SLUGS as $slug) {
            $section = PageSection::where('key', 'page.'.$slug)->firstOrFail();

            $this->get('/'.$slug)
                ->assertOk()
                ->assertSee($section->content['title'])
                ->assertSee($section->content['blocks'][0]['h']);
        }
    }

    public function test_the_contact_page_surfaces_the_support_address(): void
    {
        $this->get('/contact')
            ->assertOk()
            ->assertSee('hello@tarpalink.ph')
            ->assertSee('mailto:hello@tarpalink.ph', false);
    }

    public function test_an_unpublished_page_is_gone_rather_than_empty(): void
    {
        PageSection::where('key', 'page.terms')->update(['status' => 'draft']);

        $this->get('/terms')->assertNotFound();
    }

    public function test_a_slug_outside_the_whitelist_does_not_resolve(): void
    {
        // Without the `whereIn` constraint this would be a 200 rendering an empty
        // shell, or worse, it would shadow real routes.
        $this->get('/not-a-real-page')->assertNotFound();
    }

    public function test_the_catch_all_does_not_shadow_real_routes(): void
    {
        // `pricing` is a footer page; `shops` is a route. Both must keep working
        // now that a wildcard sits at the bottom of the route file.
        $this->get('/pricing')->assertOk();
        $this->get('/shops')->assertOk();
        $this->get('/login')->assertOk();
    }

    public function test_the_landing_page_calls_to_action_point_somewhere(): void
    {
        $props = $this->get('/')->assertOk()->viewData('props');

        // "Try the calculator" and "Open a shop account" were both `href="#"`.
        // The page is a Vue component, so the destinations arrive as props and
        // the template binds them; a null here would render href="null".
        $this->assertSame(route('shops.index'), $props['routes']['shops']);
        $this->assertSame(route('page', 'open-a-shop'), $props['routes']['openShop']);
    }
}
