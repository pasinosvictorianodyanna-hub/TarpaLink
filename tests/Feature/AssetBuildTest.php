<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The built stylesheets must be compiled, not shipped as source.
 *
 * This exists because of a bug that was invisible to every other test: the
 * Vite config was missing `@tailwindcss/vite`, so `resources/css/filament/theme.css`
 * was emitted exactly as written. The file was 376 KB, served 200 OK, and
 * styled nothing — `@apply` and `@theme` are not CSS the browser understands,
 * so the whole sheet was discarded. Every panel screen rendered unstyled and
 * read as a blank page, while HTTP checks and the smoke tests all passed.
 *
 * A stylesheet that is served is not a stylesheet that works. This checks the
 * artefact, not the response.
 */
class AssetBuildTest extends TestCase
{
    /** Directives that mean Tailwind never ran over the file. */
    private const RAW_DIRECTIVES = ['@apply', '@theme', '@source'];

    public function test_the_panel_theme_is_compiled_rather_than_shipped_as_source(): void
    {
        $path = $this->builtTheme();

        if ($path === null) {
            $this->markTestSkipped('No built theme in public/build — run `npm run build`.');
        }

        $css = (string) file_get_contents($path);

        foreach (self::RAW_DIRECTIVES as $directive) {
            $this->assertStringNotContainsString(
                $directive,
                $css,
                "{$directive} survived into the built theme, so Tailwind did not process it. "
                .'Check that @tailwindcss/vite is in vite.config.js.',
            );
        }
    }

    public function test_the_panel_theme_actually_carries_filament_rules(): void
    {
        $path = $this->builtTheme();

        if ($path === null) {
            $this->markTestSkipped('No built theme in public/build — run `npm run build`.');
        }

        $css = (string) file_get_contents($path);

        // Filament's own classes, and the paper palette the theme re-points onto
        // its grey scale. If the import ever stops resolving, both disappear.
        $this->assertStringContainsString('fi-sidebar', $css);
        $this->assertStringContainsString('#f5f3ed', strtolower($css));
    }

    /** The newest built theme, or null when nothing has been built. */
    private function builtTheme(): ?string
    {
        $files = glob(public_path('build/assets/theme-*.css')) ?: [];

        if ($files === []) {
            return null;
        }

        usort($files, fn (string $a, string $b) => filemtime($b) <=> filemtime($a));

        return $files[0];
    }
}
