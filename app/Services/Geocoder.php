<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Turning a written address into a point on the map.
 *
 * Shops were seeded with coordinates, which means a shop that moves is
 * unreachable and a new shop cannot be placed at all without a developer. This
 * looks the address up once and stores the answer on the shop.
 *
 * Nominatim is free and needs no key, but it is somebody else's server and it is
 * run on a volunteer budget. So: results are cached for a month, the request
 * carries an honest User-Agent, and nothing calls it in a loop. A failure is a
 * plain null — the caller says "could not find that address" and the customer
 * still has the map and the GPS button.
 */
final class Geocoder
{
    /** Cache lifetime. An address does not move. */
    private const TTL_DAYS = 30;

    /**
     * @return array{lat: float, lng: float, label: string}|null
     */
    public function lookup(string $address): ?array
    {
        $address = trim($address);

        if ($address === '' || ! $this->enabled()) {
            return null;
        }

        $key = 'geocode:'.sha1(mb_strtolower($address).'|'.config('services.nominatim.country'));

        // `remember` caches nulls too, which is the point: an address Nominatim
        // does not know should not be asked about again tomorrow.
        return Cache::remember($key, now()->addDays(self::TTL_DAYS), fn (): ?array => $this->ask($address));
    }

    public function enabled(): bool
    {
        return (bool) config('services.nominatim.enabled');
    }

    /** @return array{lat: float, lng: float, label: string}|null */
    private function ask(string $address): ?array
    {
        try {
            $response = Http::withHeaders([
                // Nominatim's usage policy requires identifying the application.
                // A generic curl User-Agent gets blocked, and rightly so.
                'User-Agent' => sprintf(
                    '%s/1.0 (%s)',
                    str_replace(' ', '', (string) config('app.name', 'TarpaLink')),
                    (string) config('app.url', 'http://localhost'),
                ),
                'Accept-Language' => 'en',
            ])
                ->timeout((int) config('services.nominatim.timeout', 6))
                ->get(rtrim((string) config('services.nominatim.url'), '/').'/search', [
                    'q' => $address,
                    'format' => 'jsonv2',
                    'limit' => 1,
                    'countrycodes' => config('services.nominatim.country'),
                ]);

            if (! $response->successful()) {
                return null;
            }

            $hit = $response->json('0');

            if (! is_array($hit) || ! isset($hit['lat'], $hit['lon'])) {
                return null;
            }

            $lat = (float) $hit['lat'];
            $lng = (float) $hit['lon'];

            // Trust but verify: a coordinate outside the real world would break
            // every distance on the page, and it costs two comparisons to check.
            if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
                return null;
            }

            return [
                'lat' => round($lat, 7),
                'lng' => round($lng, 7),
                'label' => (string) ($hit['display_name'] ?? $address),
            ];
        } catch (Throwable $e) {
            // No network, DNS failure, timeout: all the same to the caller.
            report($e);

            return null;
        }
    }
}
