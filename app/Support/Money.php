<?php

namespace App\Support;

/**
 * Money is integer centavos everywhere in this application. Formatting happens
 * once, at the edge — in a view or a table column — never in the middle of a
 * calculation. See docs/CONTRACT.md.
 */
final class Money
{
    public static function format(int $centavos, string $symbol = '₱'): string
    {
        return $symbol.number_format($centavos / 100, 2);
    }

    /** Compact form for dashboard tiles: ₱12.4k. */
    public static function compact(int $centavos, string $symbol = '₱'): string
    {
        $pesos = $centavos / 100;

        return match (true) {
            abs($pesos) >= 1_000_000 => $symbol.number_format($pesos / 1_000_000, 1).'M',
            abs($pesos) >= 1_000 => $symbol.number_format($pesos / 1_000, 1).'k',
            default => $symbol.number_format($pesos, 0),
        };
    }

    public static function pesos(int $centavos): float
    {
        return $centavos / 100;
    }

    /**
     * Parse "1,499.00" or "1499" into centavos.
     *
     * Deliberately string-based: routing a price through a float to get here is
     * how a ₱2,036.80 total becomes 203679. All inputs are non-negative.
     */
    public static function toCentavos(string|int|float|null $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        $normalised = preg_replace('/[^0-9.]/', '', (string) $value);

        if ($normalised === '' || $normalised === null) {
            return 0;
        }

        [$whole, $fraction] = array_pad(explode('.', $normalised, 2), 2, '0');

        $fraction = substr(str_pad($fraction, 2, '0'), 0, 2);

        return ((int) $whole) * 100 + (int) $fraction;
    }
}
