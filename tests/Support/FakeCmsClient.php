<?php

namespace Tests\Support;

use App\Services\CmsClient;

/**
 * A CMS that answers from an array.
 *
 * The real client speaks HTTP to a separate application. Tests should not need
 * that application running, so this stands in for it and keeps the same
 * contract: published content only, and a section that is not in the array
 * simply does not exist.
 */
class FakeCmsClient extends CmsClient
{
    /**
     * @param  array<string, array<string, mixed>>  $sections
     * @param  array<string, string>  $settings
     */
    public function __construct(
        private array $sections = [],
        private array $settings = [],
    ) {}

    /** @return array<string, array<string, mixed>> */
    public function sections(): array
    {
        return $this->sections;
    }

    /** @return array<string, mixed>|null */
    public function find(string $key): ?array
    {
        return $this->sections[$key] ?? null;
    }

    /** @return array<string, string> */
    public function settings(): array
    {
        return $this->settings;
    }

    public function setting(string $key, ?string $default = null): ?string
    {
        return $this->settings[$key] ?? $default;
    }

    public function online(): bool
    {
        return true;
    }
}
