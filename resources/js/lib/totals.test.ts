import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

import { divRoundHalfUp, scaleToInt } from './dec';
import { litres, money } from './money';
import { computeTotals } from './totals';
import type { TotalsLine, VatMode } from './totals';

/**
 * The same tests/fixtures/totals.json that Pest reads. The PHP implementation
 * is authoritative; this port only drives the builder's live preview. If they
 * drift apart, one of the two suites fails and CI stops the build.
 */
interface Fixture {
    name: string;
    lines: TotalsLine[];
    vat_mode: VatMode;
    vat_rate: string;
    expect: {
        subtotal_cents: number;
        vat_cents: number;
        total_cents: number;
        line_amounts_cents: number[];
    };
}

const fixtures: Fixture[] = JSON.parse(
    readFileSync(
        resolve(__dirname, '../../../tests/fixtures/totals.json'),
        'utf-8',
    ),
);

describe('computeTotals', () => {
    it('reads a fixture with cases in it', () => {
        expect(fixtures.length).toBeGreaterThanOrEqual(20);
    });

    it.each(fixtures.map((f) => [f.name, f] as const))(
        'matches the shared fixture: %s',
        (_name, fixture) => {
            const result = computeTotals(
                fixture.lines,
                fixture.vat_mode,
                fixture.vat_rate,
            );

            expect(result.subtotalCents).toBe(fixture.expect.subtotal_cents);
            expect(result.vatCents).toBe(fixture.expect.vat_cents);
            expect(result.totalCents).toBe(fixture.expect.total_cents);
            expect(result.lineAmountsCents).toEqual(
                fixture.expect.line_amounts_cents,
            );
        },
    );

    it.each(fixtures.map((f) => [f.name, f] as const))(
        'has subtotal plus VAT equal the total: %s',
        (_name, fixture) => {
            const result = computeTotals(
                fixture.lines,
                fixture.vat_mode,
                fixture.vat_rate,
            );

            expect(result.subtotalCents + result.vatCents).toBe(
                result.totalCents,
            );
        },
    );

    it('matches the acceptance-criteria load from the build brief', () => {
        const result = computeTotals(
            [{ litres: '12000', price_per_litre: '23.7000' }],
            'inclusive',
        );

        expect(result).toEqual({
            subtotalCents: 24_730_435,
            vatCents: 3_709_565,
            totalCents: 28_440_000,
            lineAmountsCents: [28_440_000],
        });

        expect(money(result.totalCents)).toBe('R284,400.00');
    });

    it('rounds each line before summing, not the other way round', () => {
        // Three lines of exactly half a cent: 1c each, 3c total. Summing first
        // would give 1.5c rounding to 2c, and the printed line amounts would no
        // longer add up to the printed total.
        const halfCent = { litres: '1.00', price_per_litre: '0.0050' };

        const result = computeTotals([halfCent, halfCent, halfCent], 'none');

        expect(result.lineAmountsCents).toEqual([1, 1, 1]);
        expect(result.totalCents).toBe(3);
    });

    it('does not lose the cent that a float implementation loses', () => {
        // 8.7 x 1.15 x 100 is exactly 1000.5, so the correct answer is 1001c.
        // In IEEE-754 the product is 1000.4999999999999 and Math.round returns
        // 1000 — one cent short, on every line, silently.
        expect(Math.round(8.7 * 1.15 * 100)).toBe(1000);

        expect(
            computeTotals(
                [{ litres: '8.70', price_per_litre: '1.1500' }],
                'none',
            ).totalCents,
        ).toBe(1001);
    });

    it('throws on a value that is not a decimal number', () => {
        expect(() =>
            computeTotals(
                [{ litres: '12,000', price_per_litre: '23.70' }],
                'none',
            ),
        ).toThrow();
    });
});

describe('rounding', () => {
    it('rounds halves towards positive infinity, matching PHP round()', () => {
        expect(divRoundHalfUp(5n, 10n)).toBe(1n); // 0.5 -> 1
        expect(divRoundHalfUp(15n, 10n)).toBe(2n); // 1.5 -> 2
        expect(divRoundHalfUp(-5n, 10n)).toBe(0n); // -0.5 -> 0
        expect(divRoundHalfUp(-15n, 10n)).toBe(-1n); // -1.5 -> -1
        expect(divRoundHalfUp(-16n, 10n)).toBe(-2n); // -1.6 -> -2
    });

    it('scales decimal strings to exact integers', () => {
        expect(scaleToInt('23.70', 4)).toBe(237000n);
        expect(scaleToInt('23.7000', 4)).toBe(237000n);
        expect(scaleToInt('12000', 2)).toBe(1200000n);
        expect(scaleToInt('', 2)).toBe(0n);
        expect(scaleToInt('-1.00', 2)).toBe(-100n);
        expect(scaleToInt('23.70005', 4)).toBe(237001n);
        expect(scaleToInt('1000.005', 2)).toBe(100001n);
    });
});

describe('money', () => {
    it('formats cents as rand, identically to App\\Support\\Money', () => {
        expect(money(8_225_000)).toBe('R82,250.00');
        expect(money(28_440_000)).toBe('R284,400.00');
        expect(money(0)).toBe('R0.00');
        expect(money(1)).toBe('R0.01');
        expect(money(-8_225_000)).toBe('-R82,250.00');
        expect(money(8_225_000, false)).toBe('82,250.00');
    });

    it('formats litres', () => {
        expect(litres('12000.00')).toBe('12,000');
        expect(litres('1234.56')).toBe('1,234.56');
        expect(litres('1234.50')).toBe('1,234.5');
        expect(litres('0')).toBe('0');
    });
});
