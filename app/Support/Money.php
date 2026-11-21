<?php

namespace App\Support;

/**
 * Money is stored as integer centavos everywhere. The panel is the one place
 * a human types pesos, so the conversion lives here instead of being scattered
 * through form definitions.
 */
final class Money
{
    public static function toPesos(?int $centavos): ?float
    {
        return $centavos === null ? null : $centavos / 100;
    }

    public static function toCentavos(float|int|string|null $pesos): int
    {
        return (int) round(((float) $pesos) * 100);
    }

    public static function format(?int $centavos): string
    {
        return $centavos === null ? '—' : '₱'.number_format($centavos / 100, 2);
    }
}
