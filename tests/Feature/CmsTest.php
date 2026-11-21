<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\PageSections\Pages\EditPageSection;
use App\Models\PageSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * docs/ISSUES.md #10: copy and order are editable, layout is not, and hiding a
 * section must not break the page.
 */
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

    public function test_editing_the_hero_headline_changes_the_landing_page(): void
    {
        $section = PageSection::where('key', 'hero')->firstOrFail();

        $this->actingAs($this->admin());

        Livewire::test(EditPageSection::class, ['record' => $section->getRouteKey()])
            ->fillForm(['content.headline' => 'Every print job, priced before you commit'])
            ->call('save')
            ->assertHasNoFormErrors();

        $fresh = $section->fresh();

        $this->assertSame('Every print job, priced before you commit', $fresh->content['headline']);

        // Sibling keys in the same JSON column must survive the round-trip.
        $this->assertSame('Print, priced before you commit', $fresh->content['eyebrow']);
        $this->assertSame('/shops', $fresh->content['primary_link']);

        $this->get('/')->assertOk()->assertSee('Every print job, priced before you commit');
    }

    public function test_editing_a_repeater_section_round_trips(): void
    {
        $section = PageSection::where('key', 'faq')->firstOrFail();

        $this->actingAs($this->admin());

        Livewire::test(EditPageSection::class, ['record' => $section->getRouteKey()])
            ->fillForm([
                'content.title' => 'Questions we get',
                'content.items' => [
                    ['q' => 'Do you deliver?', 'a' => 'Pickup at the counter only.'],
                    ['q' => 'Can I cancel?', 'a' => 'Any time before the proof is approved.'],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $content = $section->fresh()->content;

        $this->assertSame('Questions we get', $content['title']);
        $this->assertCount(2, $content['items']);
        $this->assertSame('Do you deliver?', $content['items'][0]['q']);
        $this->assertSame('Any time before the proof is approved.', $content['items'][1]['a']);
    }

    public function test_hiding_a_section_removes_it_without_breaking_the_page(): void
    {
        $hero = PageSection::where('key', 'hero')->firstOrFail();
        $headline = $hero->content['headline'];

        $this->get('/')->assertOk()->assertSee($headline);

        $hero->update(['status' => 'hidden']);

        // Hidden sections fall back to the Blade defaults rather than 500ing.
        $this->get('/')->assertOk()->assertDontSee($headline)->assertSee('Custom printing');

        // The row is still there, so it can be published again.
        $this->assertDatabaseHas('page_sections', ['key' => 'hero', 'status' => 'hidden']);
    }

    public function test_the_cms_is_closed_to_shop_owners(): void
    {
        $this->actingAs(User::where('email', 'kevin@inkwell.ph')->firstOrFail())
            ->get('/admin/page-sections')
            ->assertForbidden();
    }
}
