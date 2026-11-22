<?php

namespace App\Support;

use App\Models\Integration;
use App\Models\User;
use Throwable;

/**
 * Where a credential comes from: the database, the environment, or nowhere.
 *
 * Every reader of an API key goes through here rather than calling `config()`
 * directly, because there are now two places a key can live. The database wins
 * when it has a value — that is what makes the integrations screen editable —
 * and the environment is the fallback, so an existing deployment with keys in
 * `.env` keeps working untouched.
 *
 * Blank means fall back. Clearing a field on the screen does not disable the
 * integration; it reverts it to whatever `.env` says. That is the only rule
 * that makes "leave this empty" unambiguous, and it is what the form says.
 */
final class Credentials
{
    public const FROM_DATABASE = 'database';

    public const FROM_ENVIRONMENT = 'environment';

    public const FROM_NOWHERE = 'unset';

    /**
     * The value in force, or null when nothing is configured.
     *
     * Reads through rather than caching: a key saved on the integrations screen
     * has to take effect on the next request, and a stale cache here would mean
     * an administrator saving a correct key and still watching payments fail.
     * One indexed lookup per read is a fair price for that.
     */
    public static function get(string $provider, string $key): ?string
    {
        $stored = self::stored($provider)[$key] ?? null;

        if (filled($stored)) {
            return (string) $stored;
        }

        $configured = config("services.{$provider}.{$key}");

        return filled($configured) ? (string) $configured : null;
    }

    /** Which of the two sources is currently supplying a value. */
    public static function source(string $provider, string $key): string
    {
        if (filled(self::stored($provider)[$key] ?? null)) {
            return self::FROM_DATABASE;
        }

        return filled(config("services.{$provider}.{$key}"))
            ? self::FROM_ENVIRONMENT
            : self::FROM_NOWHERE;
    }

    /**
     * What has been saved for a provider — never the environment fallback.
     *
     * The edit form fills from this, not from `get()`, so an environment secret
     * is never copied into a page's HTML just because an administrator opened
     * the screen.
     *
     * @return array<string, string>
     */
    public static function stored(string $provider): array
    {
        try {
            $settings = Integration::where('provider', $provider)->first()?->settings;
        } catch (Throwable) {
            // No table yet — a fresh clone before `migrate`, or a database that
            // is down. Falling back to the environment beats a 500 on every
            // page that happens to read a key.
            return [];
        }

        return is_array($settings) ? $settings : [];
    }

    /**
     * Save a provider's credentials, replacing whatever was there.
     *
     * Blank fields are dropped rather than stored as empty strings, so the
     * environment fallback re-applies and `source()` tells the truth. A
     * submission with nothing in it removes the row entirely.
     *
     * @param  array<string, ?string>  $values
     */
    public static function put(string $provider, array $values, ?User $by = null): void
    {
        $clean = [];

        foreach ($values as $key => $value) {
            if (filled($value)) {
                $clean[$key] = trim((string) $value);
            }
        }

        if ($clean === []) {
            self::forget($provider);

            return;
        }

        Integration::updateOrCreate(
            ['provider' => $provider],
            ['settings' => $clean, 'updated_by' => $by?->id],
        );
    }

    public static function forget(string $provider): void
    {
        Integration::where('provider', $provider)->delete();
    }
}
