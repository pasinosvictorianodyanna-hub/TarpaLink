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

    /** Drops the cents when the amount is whole — for thresholds and averages. */
    public static function short(?int $centavos): string
    {
        if ($centavos === null) {
            return '—';
        }

        return $centavos % 100 === 0
            ? '₱'.number_format($centavos / 100, 0)
            : self::format($centavos);
    }

    /**
     * A figure that may be negative, with the sign in front of the symbol.
     *
     * `format(-1086)` reads "₱-10.86", which looks like a typo. A margin that
     * has gone negative is a real state — a small job whose processor fee
     * exceeds the commission — so it needs to read as one number.
     */
    public static function signed(int $centavos): string
    {
        return $centavos < 0 ? '−'.self::short(-$centavos) : self::short($centavos);
    }
}
