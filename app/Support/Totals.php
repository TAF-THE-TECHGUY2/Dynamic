<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\VatMode;

/**
 * The authoritative totals calculation. Nothing else writes totals to the
 * database — a total arriving from the browser is display state, never truth.
 *
 * resources/js/lib/totals.ts is a line-for-line port used only to drive the
 * builder's live preview. tests/fixtures/totals.json is read by both Pest and
 * Vitest, so the two implementations cannot drift apart without failing CI.
 *
 * Rounding: each line is rounded to a whole cent first, then the rounded line
 * amounts are summed. Never sum unrounded values and round at the end — line
 * amounts are printed individually on the document and must add up to the
 * total exactly.
 */
final class Totals
{
    /**
     * @param  array<int, array{litres?: string|null, price_per_litre?: string|null, amount_cents?: int|string|null}>  $lines
     *                                                                                                                         Lines priced per litre supply `litres` and `price_per_litre`. Flat-charge lines
     *                                                                                                                         (delivery, standing charges) may instead supply `amount_cents` directly.
     * @param  string  $vatRate  decimal string, e.g. '0.15'
     */
    public static function compute(
        array $lines,
        VatMode $vatMode,
        string $vatRate = '0.15',
    ): TotalsResult {
        // Rate scaled to four decimals, matching documents.vat_rate decimal(5,4).
        $rate = Dec::scaleToInt($vatRate, 4);

        $lineAmountsCents = [];
        $sum = '0';

        foreach ($lines as $line) {
            $amount = self::lineAmountCents($line);

            $lineAmountsCents[] = (int) $amount;
            $sum = bcadd($sum, $amount, 0);
        }

        [$subtotal, $vat, $total] = match ($vatMode) {
            VatMode::None => [$sum, '0', $sum],

            VatMode::Exclusive => (function () use ($sum, $rate): array {
                $vat = Dec::divRoundHalfUp(bcmul($sum, $rate, 0), '10000');

                return [$sum, $vat, bcadd($sum, $vat, 0)];
            })(),

            VatMode::Inclusive => (function () use ($sum, $rate): array {
                // net = total / (1 + rate), with both sides scaled by 10^4.
                $net = Dec::divRoundHalfUp(
                    bcmul($sum, '10000', 0),
                    bcadd('10000', $rate, 0),
                );

                return [$net, bcsub($sum, $net, 0), $sum];
            })(),
        };

        return new TotalsResult(
            subtotalCents: (int) $subtotal,
            vatCents: (int) $vat,
            totalCents: (int) $total,
            lineAmountsCents: $lineAmountsCents,
        );
    }

    /**
     * @param  array{litres?: string|null, price_per_litre?: string|null, amount_cents?: int|string|null}  $line
     * @return numeric-string
     */
    private static function lineAmountCents(array $line): string
    {
        $litres = $line['litres'] ?? null;
        $price = $line['price_per_litre'] ?? null;

        // A flat-charge line carries its amount directly and is not multiplied out.
        if (($litres === null || $litres === '') && ($price === null || $price === '')) {
            return Dec::scaleToInt((string) ($line['amount_cents'] ?? '0'), 0);
        }

        // litres (10^2) x price (10^4) lands in units of 10^-6 rand.
        // Cents are 10^-2 rand, so divide by 10^4 and round the half up.
        return Dec::divRoundHalfUp(
            bcmul(
                Dec::scaleToInt((string) ($litres ?? '0'), 2),
                Dec::scaleToInt((string) ($price ?? '0'), 4),
                0,
            ),
            '10000',
        );
    }
}
