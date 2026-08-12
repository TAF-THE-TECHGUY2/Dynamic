<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * Exact decimal helpers built on arbitrary-precision integer strings.
 *
 * Diesel is sold in thousands of litres, so a half-cent rounding error on a
 * 15,000 litre order becomes a customer query on an invoice. Nothing in the
 * money path is allowed to touch a float — every value travels as a decimal
 * string and lands as an integer number of cents.
 *
 * The TypeScript port in resources/js/lib/dec.ts is line-for-line identical,
 * with BigInt standing in for bcmath. Keep them in step: tests/fixtures/totals.json
 * is read by both suites and divergence fails CI.
 */
final class Dec
{
    /**
     * Parse a decimal string into an integer scaled by 10^$scale.
     *
     * '23.70' at scale 4 becomes '237000'. Extra precision beyond $scale is
     * rounded half up, matching what PostgreSQL does when the value lands in
     * a numeric column.
     *
     * @return numeric-string an integer, with no decimal point
     */
    public static function scaleToInt(string $value, int $scale): string
    {
        $value = trim($value);

        if ($value === '' || $value === '-' || $value === '+') {
            $value = '0';
        }

        if (preg_match('/^[+-]?\d+(\.\d+)?$/', $value) !== 1) {
            throw new InvalidArgumentException("Not a decimal number: [{$value}]");
        }

        $negative = str_starts_with($value, '-');
        $digits = ltrim($value, '+-');

        [$whole, $fraction] = str_contains($digits, '.')
            ? explode('.', $digits, 2)
            : [$digits, ''];

        if (strlen($fraction) <= $scale) {
            $scaled = $whole.str_pad($fraction, $scale, '0');

            return self::normalise($negative ? '-'.$scaled : $scaled);
        }

        // More precision than we can store: round half up to $scale.
        $exact = self::normalise(($negative ? '-' : '').$whole.$fraction);

        /** @var numeric-string $divisor */
        $divisor = bcpow('10', (string) (strlen($fraction) - $scale), 0);

        return self::divRoundHalfUp($exact, $divisor);
    }

    /**
     * Render an integer scaled by 10^$scale back as a decimal string.
     *
     * fromScaledInt('237000', 4) === '23.7000' — the shape decimal(10,4) holds.
     *
     * @param  numeric-string  $scaled
     */
    public static function fromScaledInt(string $scaled, int $scale): string
    {
        if ($scale <= 0) {
            return $scaled;
        }

        $negative = str_starts_with($scaled, '-');
        $digits = str_pad(ltrim($scaled, '-'), $scale + 1, '0', STR_PAD_LEFT);

        $whole = substr($digits, 0, -$scale);
        $fraction = substr($digits, -$scale);

        return ($negative ? '-' : '').$whole.'.'.$fraction;
    }

    /**
     * Divide $n by a positive $d, rounding halves up (towards +infinity).
     *
     * This is exactly what PHP's round() and JavaScript's Math.round() do for
     * positive numbers, which is why the two implementations agree.
     *
     * @param  numeric-string  $n
     * @param  numeric-string  $d
     * @return numeric-string
     */
    public static function divRoundHalfUp(string $n, string $d): string
    {
        if (bccomp($d, '0', 0) <= 0) {
            throw new InvalidArgumentException("Divisor must be positive, got [{$d}]");
        }

        // floor((n + d/2) / d), written as floor((2n + d) / 2d) so an odd
        // divisor cannot lose a half on the way in.
        return self::floorDiv(
            bcadd(bcmul($n, '2', 0), $d, 0),
            bcmul($d, '2', 0),
        );
    }

    /**
     * Integer division rounding towards negative infinity, for a positive $d.
     *
     * bcdiv truncates towards zero, which is not the same thing once the
     * numerator goes negative — a credit line would round the wrong way.
     *
     * @param  numeric-string  $n
     * @param  numeric-string  $d
     * @return numeric-string
     */
    public static function floorDiv(string $n, string $d): string
    {
        $quotient = bcdiv($n, $d, 0);
        $remainder = bcsub($n, bcmul($quotient, $d, 0), 0);

        if (bccomp($remainder, '0', 0) < 0) {
            $quotient = bcsub($quotient, '1', 0);
        }

        return self::normalise($quotient);
    }

    /**
     * Strip leading zeros and a redundant minus sign so equal values compare
     * equal as strings.
     *
     * @return numeric-string
     */
    private static function normalise(string $value): string
    {
        $negative = str_starts_with($value, '-');
        $digits = ltrim(ltrim($value, '+-'), '0');

        if ($digits === '') {
            return '0';
        }

        $result = $negative ? '-'.$digits : $digits;

        // Everything reaching here was validated as a decimal on the way in, so
        // this is an invariant rather than a branch anyone should hit. It is
        // spelled out because the whole money path depends on it holding.
        if (! is_numeric($result)) {
            throw new InvalidArgumentException("Not a decimal number: [{$value}]");
        }

        return $result;
    }
}
