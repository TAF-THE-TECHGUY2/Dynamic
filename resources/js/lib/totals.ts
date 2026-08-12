import { divRoundHalfUp, scaleToInt } from './dec';

/**
 * Preview-only port of app/Support/Totals.php.
 *
 * The builder needs totals client-side so the A4 preview updates as you type.
 * That is the only reason this exists. It never posts a total to the server:
 * the server recomputes on save with Totals::compute() and the stored value
 * wins. Treat anything computed here as pixels, not money.
 *
 * tests/fixtures/totals.json is read by both this suite and Pest. If the two
 * implementations drift, CI fails.
 */

export type VatMode = 'none' | 'exclusive' | 'inclusive';

export interface TotalsLine {
    /** Decimal string, up to 2 places — matches document_lines.litres. */
    litres?: string | null;
    /** Decimal string, up to 4 places — matches document_lines.price_per_litre. */
    price_per_litre?: string | null;
    /** Flat-charge lines (delivery, standing charges) carry their amount directly. */
    amount_cents?: number | string | null;
}

export interface TotalsResult {
    subtotalCents: number;
    vatCents: number;
    totalCents: number;
    /** One rounded amount per input line, in order. */
    lineAmountsCents: number[];
}

/**
 * Each line is rounded to a whole cent first, then the rounded line amounts are
 * summed. Never sum unrounded values and round at the end — line amounts are
 * printed individually on the document and must add up to the total exactly.
 */
export function computeTotals(
    lines: TotalsLine[],
    vatMode: VatMode,
    vatRate: string = '0.15',
): TotalsResult {
    // Rate scaled to four decimals, matching documents.vat_rate decimal(5,4).
    const rate = scaleToInt(vatRate, 4);

    const lineAmountsCents: bigint[] = [];
    let sum = 0n;

    for (const line of lines) {
        const amount = lineAmountCents(line);

        lineAmountsCents.push(amount);
        sum += amount;
    }

    let subtotal: bigint;
    let vat: bigint;
    let total: bigint;

    if (vatMode === 'none') {
        subtotal = sum;
        vat = 0n;
        total = sum;
    } else if (vatMode === 'exclusive') {
        subtotal = sum;
        vat = divRoundHalfUp(sum * rate, 10000n);
        total = sum + vat;
    } else {
        // net = total / (1 + rate), with both sides scaled by 10^4.
        const net = divRoundHalfUp(sum * 10000n, 10000n + rate);

        subtotal = net;
        vat = sum - net;
        total = sum;
    }

    return {
        subtotalCents: Number(subtotal),
        vatCents: Number(vat),
        totalCents: Number(total),
        lineAmountsCents: lineAmountsCents.map(Number),
    };
}

function lineAmountCents(line: TotalsLine): bigint {
    const litres = line.litres ?? null;
    const price = line.price_per_litre ?? null;

    // A flat-charge line carries its amount directly and is not multiplied out.
    if (
        (litres === null || litres === '') &&
        (price === null || price === '')
    ) {
        return scaleToInt(String(line.amount_cents ?? '0'), 0);
    }

    // litres (10^2) x price (10^4) lands in units of 10^-6 rand.
    // Cents are 10^-2 rand, so divide by 10^4 and round the half up.
    return divRoundHalfUp(
        scaleToInt(String(litres ?? '0'), 2) *
            scaleToInt(String(price ?? '0'), 4),
        10000n,
    );
}
