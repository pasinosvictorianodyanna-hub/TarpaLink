<?php

namespace Tests\Feature;

use App\Http\Controllers\PageController;
use Database\Seeders\ProductionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What a production deploy must serve after its one seeding step.
 *
 * This exists because it did not: `ProductionSeeder` seeded the homepage
 * sections but not the `page.*` rows the footer links resolve to, so a freshly
 * deployed site answered 404 to every footer link while the homepage looked
 * perfectly fine. Nothing in the UI explained it.
 */
class ProductionSeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_production_seeded_database_serves_every_footer_page(): void
    {
        $this->artisan('db:seed', ['--class' => ProductionSeeder::class, '--force' => true])
            ->assertSuccessful();

        foreach (PageController::SLUGS as $slug) {
            $this->get('/'.$slug)->assertOk();
        }
    }

    public function test_a_production_seeded_database_renders_the_homepage(): void
    {
        $this->artisan('db:seed', ['--class' => ProductionSeeder::class, '--force' => true])
            ->assertSuccessful();

        $this->get('/')->assertOk();
    }

    public function test_the_production_seeder_can_run_twice(): void
    {
        $this->artisan('db:seed', ['--class' => ProductionSeeder::class, '--force' => true])
            ->assertSuccessful();

        $this->artisan('db:seed', ['--class' => ProductionSeeder::class, '--force' => true])
            ->assertSuccessful();
    }
}
