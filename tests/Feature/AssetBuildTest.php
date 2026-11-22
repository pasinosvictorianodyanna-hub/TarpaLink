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

    /**
     * The map is the only screen that needs Leaflet, and Leaflet is ~150 kB.
     *
     * It is loaded through a dynamic import so the directory pays for it and
     * every other page does not. That is easy to undo by accident — change the
     * registry entry back to a static import and the bundle silently grows by
     * 150 kB on the landing page, with nothing failing. This checks the built
     * artefacts, because that is where the mistake would show up.
     */
    public function test_the_map_ships_as_its_own_chunk_not_in_the_main_bundle(): void
    {
        $manifest = $this->manifest();

        if ($manifest === null) {
            $this->markTestSkipped('No built manifest in public/build — run `npm run build`.');
        }

        $entry = $manifest['resources/js/app.js'] ?? null;
        $map = $manifest['resources/js/components/ShopMap.vue'] ?? null;

        $this->assertNotNull($map, 'ShopMap should be a dynamic entry in the manifest');
        $this->assertContains(
            'resources/js/components/ShopMap.vue',
            $entry['dynamicImports'] ?? [],
            'app.js should import ShopMap dynamically, or every page downloads Leaflet',
        );

        $app = (string) file_get_contents(public_path('build/'.$entry['file']));
        $chunk = (string) file_get_contents(public_path('build/'.$map['file']));

        $this->assertStringNotContainsString('leaflet', strtolower($app), 'Leaflet leaked into the main bundle');
        $this->assertStringContainsString('leaflet-container', $chunk, 'The map chunk does not contain Leaflet');

        // Leaflet's stylesheet has to travel with the chunk, or the map renders
        // as a stack of unstyled divs the first time the panel opens.
        $this->assertNotEmpty($map['css'] ?? [], 'The map chunk should carry Leaflet’s stylesheet');
    }

    /** The build manifest, or null when nothing has been built. */
    private function manifest(): ?array
    {
        $path = public_path('build/manifest.json');

        return is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
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
