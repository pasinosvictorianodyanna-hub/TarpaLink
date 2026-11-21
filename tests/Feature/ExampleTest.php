<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Storefront smoke test. The landing page reads shops and CMS sections from the
 * database, so it needs a seeded database to be meaningful.
 */
class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_storefront_pages_render(): void
    {
        $this->seed();

        $this->get('/')->assertOk();
        $this->get('/shops')->assertOk();
        $this->get('/shops/inkwell-press')->assertOk();
        $this->get('/shops/inkwell-press/build')->assertOk();
    }
}
