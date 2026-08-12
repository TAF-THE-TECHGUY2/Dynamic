/**
 * Rand formatting — the TypeScript half of app/Support/Money.php.
 *
 * Every number the admin console renders goes through here or through the
 * `font-mono` class, because a fuel depot's figures should line up.
 */

/** money(8225000) === 'R82,250.00' */
export function money(cents: number, symbol: boolean = true): string {
    const negative = cents < 0;
    const absolute = Math.abs(Math.trunc(cents));

    const rands = Math.floor(absolute / 100);
    const remainder = absolute % 100;

    const formatted =
        groupThousands(String(rands)) +
        '.' +
        String(remainder).padStart(2, '0');

    return `${negative ? '-' : ''}${symbol ? 'R' : ''}${formatted}`;
}

/** Compact rand for metric cards: compactMoney(437000000) === 'R4.37m' */
export function compactMoney(cents: number): string {
    const negative = cents < 0;
    const rands = Math.abs(cents) / 100;

    let formatted: string;

    if (rands >= 1_000_000) {
        formatted = `${(rands / 1_000_000).toFixed(2)}m`;
    } else if (rands >= 10_000) {
        formatted = `${(rands / 1_000).toFixed(1)}k`;
    } else {
        formatted = groupThousands(String(Math.round(rands)));
    }

    return `${negative ? '-' : ''}R${formatted}`;
}

/** litres('12000.00') === '12,000' */
export function litres(value: string | number | null | undefined): string {
    if (value === null || value === undefined || value === '') {
        return '0';
    }

    const text = String(value).trim();

    if (!/^[+-]?\d+(\.\d+)?$/.test(text)) {
        return '0';
    }

    const negative = text.startsWith('-');
    const digits = text.replace(/^[+-]/, '');
    const dot = digits.indexOf('.');

    const whole = dot === -1 ? digits : digits.slice(0, dot);
    const fraction = (dot === -1 ? '' : digits.slice(dot + 1, dot + 3)).replace(
        /0+$/,
        '',
    );

    const grouped = groupThousands(whole.replace(/^0+(?=\d)/, ''));

    return `${negative ? '-' : ''}${grouped}${fraction ? `.${fraction}` : ''}`;
}

/**
 * Keep grouping identical to PHP's number_format() in every JS runtime.
 * Intl's en-ZA separator varies between a space, NBSP and narrow NBSP across
 * Node/browser ICU builds, but customer-facing documents require commas.
 */
function groupThousands(digits: string): string {
    return digits.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

/**
 * Tidy a stored decimal for display in an editable input.
 *
 * The columns hold four decimals for prices and two for litres, but nobody
 * wants to edit '23.7000' or '12000.00'. Trailing zeros are trimmed down to
 * `minDecimals`; real precision is left alone, so '23.7050' stays as it is.
 */
export function decimalInput(
    value: string | number | null | undefined,
    minDecimals: number = 0,
): string {
    if (value === null || value === undefined || value === '') {
        return '';
    }

    const text = String(value).trim();

    if (!/^[+-]?\d+(\.\d+)?$/.test(text) || !text.includes('.')) {
        return text;
    }

    const [whole, fraction] = text.split('.');
    const trimmed = fraction.replace(/0+$/, '').padEnd(minDecimals, '0');

    return trimmed === '' ? whole : `${whole}.${trimmed}`;
}

/** Rand-per-litre for the pump panel and line items: rate('23.7000') === 'R23.70' */
export function rate(value: string | number | null | undefined): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    const cents = Math.round(Number(value) * 100);

    return money(cents);
}
