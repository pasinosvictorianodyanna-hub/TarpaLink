<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\PageSections\Pages\ListPageSections;
use App\Models\PageSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CmsTest extends TestCase
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

    private function section(string $key): PageSection
    {
        return PageSection::where('key', $key)->firstOrFail();
    }

    /** Issue #10 acceptance: edit the hero in the panel, the landing page changes. */
    public function test_editing_the_hero_headline_changes_the_landing_page(): void
    {
        $hero = $this->section('hero');

        $content = $hero->content;
        $content['headline'] = 'Printing priced like a receipt, not a negotiation.';

        $hero->update(['content' => $content]);

        // The last word is wrapped in the accent stroke, so the headline is two
        // text nodes in the markup. Asserting the phrase either side of the split
        // is what "the CMS copy reached the page" actually means here.
        $this->get('/')
            ->assertOk()
            ->assertSee('Printing priced like a receipt, not a')
            ->assertSee('negotiation.');
    }

    public function test_editing_a_testimonial_changes_the_landing_page(): void
    {
        $section = $this->section('testimonials');

        $content = $section->content;
        $content['items'][0]['quote'] = 'The price was on screen before I even messaged them.';

        $section->update(['content' => $content]);

        $this->get('/')->assertOk()->assertSee('The price was on screen before I even messaged them.');
    }

    /** Issue #10 acceptance: deleting a section hides it instead of breaking the page. */
    public function test_deleting_the_banner_hides_it_without_breaking_the_page(): void
    {
        // The banner ships as a draft because the design has no announcement bar,
        // so publish it first — this test is about deletion, not default visibility.
        $this->section('banner')->update(['status' => 'published']);

        $this->get('/')->assertOk()->assertSee('Free proof on every job over');

        $this->section('banner')->delete();

        $this->get('/')
            ->assertOk()
            ->assertDontSee('Free proof on every job over');
    }

    public function test_setting_a_section_to_hidden_removes_it(): void
    {
        $faq = $this->section('faq');

        $this->get('/')->assertOk()->assertSee('Do I pay online?');

        $faq->update(['status' => 'hidden']);

        $this->get('/')->assertOk()->assertDontSee('Do I pay online?');
    }

    public function test_the_footer_blurb_comes_from_the_cms(): void
    {
        $footer = $this->section('footer');

        $footer->update(['content' => ['blurb' => 'Edited from the admin panel, no deploy required.']]);

        $this->get('/')->assertOk()->assertSee('Edited from the admin panel, no deploy required.');
    }

    public function test_the_admin_can_open_the_site_content_resource(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/site-content')
            ->assertSuccessful()
            ->assertSee('Homepage hero');

        Livewire::actingAs($this->admin())
            ->test(ListPageSections::class)
            ->assertSuccessful();
    }

    public function test_the_admin_can_open_site_settings(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/site-settings')
            ->assertSuccessful()
            ->assertSee('support_email');
    }

    public function test_a_shop_owner_cannot_reach_the_cms(): void
    {
        $owner = User::where('email', 'kevin@inkwell.ph')->firstOrFail();

        $this->actingAs($owner)->get('/admin/site-content')->assertForbidden();
    }
}
