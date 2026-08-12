import Alpine from 'alpinejs';

/**
 * The public site's entire JavaScript budget.
 *
 * Marketing pages have to rank on Google and load instantly for someone
 * standing next to a truck on mobile data, so this is Alpine and nothing else —
 * no hydration, no framework, no bundle worth mentioning.
 */

const REDUCED_MOTION = '(prefers-reduced-motion: reduce)';

/**
 * The pump counter.
 *
 * The final price is already in the server HTML, so it is visible without
 * JavaScript and indexable. This animates up to that value; it never renders
 * zeros and fills them in afterwards. If the reader has asked for reduced
 * motion, or JavaScript never arrives, the rendered value simply stands.
 */
Alpine.data('pumpCounter', (finalValue: number, durationMs: number = 800) => ({
    shown: finalValue,

    init() {
        if (window.matchMedia(REDUCED_MOTION).matches) {
            return;
        }

        const start = performance.now();

        const step = (now: number) => {
            const elapsed = now - start;

            if (elapsed >= durationMs) {
                this.shown = finalValue;

                return;
            }

            // Ease out, so the digits decelerate into place like a pump dial
            // rather than stopping dead.
            const progress = 1 - Math.pow(1 - elapsed / durationMs, 3);

            this.shown = finalValue * progress;

            requestAnimationFrame(step);
        };

        this.shown = 0;
        requestAnimationFrame(step);
    },

    get display(): string {
        return `R${this.shown.toFixed(2)}`;
    },
}));

/** The mobile nav disclosure. */
Alpine.data('siteNav', () => ({
    open: false,

    toggle() {
        this.open = !this.open;
    },
}));

interface CalculatorTotals {
    price_per_litre?: string;
    price_per_litre_display: string;
    litres_display: string;
    subtotal_display: string;
    vat_display: string;
    total_display: string;
    shows_vat: boolean;
    below_minimum?: boolean;
}

const EMPTY_TOTALS: CalculatorTotals = {
    price_per_litre_display: '—',
    litres_display: '—',
    subtotal_display: '—',
    vat_display: '—',
    total_display: '—',
    shows_vat: false,
};

/**
 * The public estimate calculator.
 *
 * Deliberately does not do the arithmetic. It debounces a POST to
 * /quote/calculate, which calls App\Support\Totals — the same code that prices
 * a real document. A third implementation of the money maths, in Alpine, is
 * exactly the kind of thing that quietly disagrees with the other two.
 */
Alpine.data(
    'quoteCalculator',
    (config: {
        endpoint: string;
        csrf: string;
        whatsapp: string;
        minimum: number;
    }) => ({
        gradeId: '',
        litres: '',
        fulfilment: 'delivery',
        minimum: config.minimum,
        belowMinimum: false,
        totals: { ...EMPTY_TOTALS },

        init() {
            const form = this.$el as HTMLFormElement;

            this.gradeId =
                form.querySelector<HTMLSelectElement>('[name=grade_id]')
                    ?.value ?? '';
            this.litres =
                form.querySelector<HTMLInputElement>('[name=litres]')?.value ??
                '';
            this.fulfilment =
                form.querySelector<HTMLSelectElement>('[name=fulfilment]')
                    ?.value ?? 'delivery';

            this.recalculate();
        },

        async recalculate() {
            if (!this.gradeId || !this.litres || Number(this.litres) <= 0) {
                this.totals = { ...EMPTY_TOTALS };
                this.belowMinimum = false;

                return;
            }

            try {
                const response = await fetch(config.endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': config.csrf,
                    },
                    body: JSON.stringify({
                        grade_id: this.gradeId,
                        litres: this.litres,
                    }),
                });

                if (!response.ok) {
                    this.totals = { ...EMPTY_TOTALS };

                    return;
                }

                const data = await response.json();

                if (!data.available) {
                    this.totals = { ...EMPTY_TOTALS };
                    this.belowMinimum = false;

                    return;
                }

                this.totals = data as CalculatorTotals;
                this.belowMinimum =
                    Boolean(data.below_minimum) &&
                    this.fulfilment === 'delivery';
            } catch {
                // Offline in the yard is a normal condition. Leave the last good
                // figures on screen rather than blanking them out.
            }
        },

        get whatsappHref(): string {
            const lines = [
                'Hi Dynamic Solutions, I would like a diesel quote.',
                '',
                `Grade: ${this.gradeLabel}`,
                `Litres: ${this.totals.litres_display}`,
                `Rate: ${this.totals.price_per_litre_display}/L`,
                `Estimated total: ${this.totals.total_display}`,
                `Fulfilment: ${this.fulfilment === 'delivery' ? 'Delivery' : 'Collection at yard'}`,
            ];

            return `https://wa.me/${config.whatsapp}?text=${encodeURIComponent(lines.join('\n'))}`;
        },

        get gradeLabel(): string {
            const select = (
                this.$el as HTMLFormElement
            ).querySelector<HTMLSelectElement>('[name=grade_id]');

            return select?.selectedOptions[0]?.textContent?.trim() ?? '';
        },
    }),
);

Alpine.start();

declare global {
    interface Window {
        Alpine: typeof Alpine;
    }
}

window.Alpine = Alpine;
