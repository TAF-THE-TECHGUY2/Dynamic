<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Rand formatting and parsing. Money is stored, passed around and compared as
 * an integer number of cents; this class is the only place it turns into text.
 */
final class Money
{
    /**
     * Format cents as South African rand: 8225000 becomes 'R82,250.00'.
     */
    public static function format(int $cents, bool $symbol = true): string
    {
        $negative = $cents < 0;
        $absolute = abs($cents);

        $rands = intdiv($absolute, 100);
        $remainder = $absolute % 100;

        $formatted = number_format($rands, 0, '.', ',').'.'.str_pad((string) $remainder, 2, '0', STR_PAD_LEFT);

        return ($negative ? '-' : '').($symbol ? 'R' : '').$formatted;
    }

    /**
     * Compact rand for dashboard metric cards: 437000000 becomes 'R4.37m'.
     *
     * Only ever used for at-a-glance figures. Anything a customer could hold
     * you to renders through format().
     */
    public static function compact(int $cents): string
    {
        $negative = $cents < 0;
        $rands = abs($cents) / 100;

        $formatted = match (true) {
            $rands >= 1_000_000 => number_format($rands / 1_000_000, 2, '.', '').'m',
            $rands >= 10_000 => number_format($rands / 1_000, 1, '.', '').'k',
            default => number_format($rands, 0, '.', ','),
        };

        return ($negative ? '-' : '').'R'.$formatted;
    }

    /**
     * Turn a rand decimal string ('23.70') into cents (2370). Exact — no floats.
     */
    public static function toCents(string $rands): int
    {
        return (int) Dec::scaleToInt($rands, 2);
    }

    /**
     * Turn cents back into a plain decimal string ('82250.00') for form inputs.
     */
    public static function toDecimalString(int $cents): string
    {
        $negative = $cents < 0;
        $absolute = abs($cents);

        return ($negative ? '-' : '')
            .intdiv($absolute, 100)
            .'.'
            .str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Litres, always with a thousands separator and two decimals trimmed when
     * whole: 12000.00 becomes '12,000'.
     */
    public static function litres(string $litres): string
    {
        $scaled = Dec::scaleToInt($litres, 2);
        $negative = str_starts_with($scaled, '-');
        $absolute = ltrim($scaled, '-');

        $whole = bcdiv($absolute, '100', 0);
        $fraction = (int) bcsub($absolute, bcmul($whole, '100', 0), 0);

        $text = number_format((float) $whole, 0, '.', ',');

        if ($fraction !== 0) {
            $text .= '.'.rtrim(str_pad((string) $fraction, 2, '0', STR_PAD_LEFT), '0');
        }

        return ($negative ? '-' : '').$text;
    }
}
