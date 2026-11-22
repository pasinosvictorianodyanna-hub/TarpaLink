<?php

namespace Tests;

use App\Services\CmsClient;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\FakeCmsClient;

abstract class TestCase extends BaseTestCase
{
    /**
     * Every test gets a CMS that answers.
     *
     * The storefront's copy now arrives over HTTP from a different application,
     * and a test suite that needs a second server running is a test suite people
     * stop running. The fake is installed here rather than in each test so the
     * only tests that mention the CMS are the ones actually about it.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeCms();
    }

    /**
     * Point the storefront at a CMS serving specific content.
     *
     * Passing nothing installs the captured production copy, so a test that just
     * wants the landing page to look normal can say so in one line.
     *
     * @param  array<string, array<string, mixed>>|null  $sections
     * @param  array<string, string>  $settings
     */
    protected function fakeCms(?array $sections = null, array $settings = []): static
    {
        [$defaultSections, $defaultSettings] = require __DIR__.'/Fixtures/content.php';

        $this->app->instance(CmsClient::class, new FakeCmsClient(
            $sections ?? $defaultSections,
            $settings === [] ? $defaultSettings : $settings,
        ));

        return $this;
    }

    /**
     * A CMS that is not answering — the storefront must still render.
     */
    protected function cmsIsDown(): static
    {
        $this->app->instance(CmsClient::class, new FakeCmsClient([], []));

        return $this;
    }
}
