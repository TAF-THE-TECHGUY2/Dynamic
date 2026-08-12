/**
 * Exact decimal helpers — a line-for-line port of app/Support/Dec.php, with
 * BigInt standing in for bcmath.
 *
 * Nothing here uses a float. `12000 * 23.7 * 100` in IEEE-754 is
 * 28439999.999999996, and a value landing exactly on a half cent is precisely
 * where the PHP and JavaScript implementations would silently disagree. Scaling
 * to integers first removes the question.
 *
 * Keep this in step with the PHP: tests/fixtures/totals.json is read by both
 * Pest and Vitest, and divergence fails CI.
 */

const DECIMAL = /^[+-]?\d+(\.\d+)?$/;

/**
 * Parse a decimal string into an integer scaled by 10^scale.
 *
 * '23.70' at scale 4 becomes 237000n. Extra precision beyond `scale` is
 * rounded half up, matching what PostgreSQL does when the value lands in a
 * numeric column.
 */
export function scaleToInt(value: string, scale: number): bigint {
    let text = value.trim();

    if (text === '' || text === '-' || text === '+') {
        text = '0';
    }

    if (!DECIMAL.test(text)) {
        throw new Error(`Not a decimal number: [${text}]`);
    }

    const negative = text.startsWith('-');
    const digits = text.replace(/^[+-]/, '');

    const dot = digits.indexOf('.');
    const whole = dot === -1 ? digits : digits.slice(0, dot);
    const fraction = dot === -1 ? '' : digits.slice(dot + 1);

    if (fraction.length <= scale) {
        const scaled = BigInt(whole + fraction.padEnd(scale, '0'));

        return negative ? -scaled : scaled;
    }

    // More precision than we can store: round half up to `scale`.
    const exact = BigInt(whole + fraction) * (negative ? -1n : 1n);
    const divisor = 10n ** BigInt(fraction.length - scale);

    return divRoundHalfUp(exact, divisor);
}

/**
 * Divide n by a positive d, rounding halves up (towards +infinity).
 *
 * This is what PHP's round() and JavaScript's Math.round() both do for
 * positive numbers, which is why the two implementations agree.
 */
export function divRoundHalfUp(n: bigint, d: bigint): bigint {
    if (d <= 0n) {
        throw new Error(`Divisor must be positive, got [${d}]`);
    }

    // floor((n + d/2) / d), written as floor((2n + d) / 2d) so an odd divisor
    // cannot lose a half on the way in.
    return floorDiv(n * 2n + d, d * 2n);
}

/**
 * Integer division rounding towards negative infinity, for a positive d.
 *
 * BigInt division truncates towards zero, which is not the same thing once the
 * numerator goes negative — a credit line would round the wrong way.
 */
export function floorDiv(n: bigint, d: bigint): bigint {
    const quotient = n / d;

    return n % d < 0n ? quotient - 1n : quotient;
}
