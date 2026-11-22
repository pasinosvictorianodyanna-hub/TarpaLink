<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The engine's only way of reading storefront copy.
 *
 * Before the split, controllers called `PageSection::content()` and read a table
 * in this application's own database. They now call this, and this calls the
 * CMS. There is no content table here to fall back to — which is the point: a
 * draft cannot leak into the storefront because it never crosses the wire, and
 * the CMS can rewrite its schema without a migration here.
 *
 * Failure is handled by returning nothing rather than throwing. Every caller
 * already passes defaults, so a CMS outage degrades the page (hero copy reverts
 * to the built-in defaults, the banner and testimonials disappear) instead of
 * returning a 500. A storefront that loses its announcement bar is a bad
 * afternoon; a storefront that 500s is an outage.
 */
class CmsClient
{
    /** @var array<string, mixed>|null Memoised per request — one HTTP call, not eight. */
    private ?array $payload = null;

    /** @var array<string, string>|null */
    private ?array $settings = null;

    /**
     * Every published section, keyed by section key.
     *
     * @return array<string, array<string, mixed>>
     */
    public function sections(): array
    {
        $sections = [];

        foreach ($this->payload()['sections'] ?? [] as $section) {
            if (isset($section['key'])) {
                $sections[$section['key']] = $section['content'] ?? [];
            }
        }

        return $sections;
    }

    /**
     * Content for a section, merged over defaults so a missing key never breaks
     * a view — the same contract the old model method offered.
     *
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>
     */
    public function section(string $key, array $defaults = []): array
    {
        return array_merge($defaults, $this->sections()[$key] ?? []);
    }

    /**
     * A section's content, or null when it is missing or unpublished.
     *
     * Null is meaningful: callers omit the block entirely rather than rendering
     * an empty shell, so unpublishing a section hides it.
     *
     * @return array<string, mixed>|null
     */
    public function find(string $key): ?array
    {
        return $this->sections()[$key] ?? null;
    }

    public function setting(string $key, ?string $default = null): ?string
    {
        return $this->settings()[$key] ?? $default;
    }

    /** @return array<string, string> */
    public function settings(): array
    {
        if ($this->settings !== null) {
            return $this->settings;
        }

        $response = $this->call('/api/v1/settings');

        return $this->settings = array_map(
            fn (mixed $value): string => (string) $value,
            $response['settings'] ?? [],
        );
    }

    /** Whether the CMS is answering at all — used by the health endpoint. */
    public function online(): bool
    {
        return $this->call('/api/v1/content') !== null;
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return $this->payload ??= $this->call('/api/v1/content') ?? [];
    }

    /** @return array<string, mixed>|null */
    private function call(string $path): ?array
    {
        $base = rtrim((string) config('services.cms.url'), '/');

        if ($base === '') {
            return null;
        }

        try {
            $response = Http::withToken((string) config('services.cms.token'))
                ->acceptJson()
                ->timeout(3)
                ->connectTimeout(2)
                ->get($base.$path);
        } catch (Throwable $e) {
            Log::warning('CMS unreachable', ['path' => $path, 'error' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('CMS refused the call', ['path' => $path, 'status' => $response->status()]);

            return null;
        }

        $json = $response->json();

        return is_array($json) ? $json : null;
    }
}
