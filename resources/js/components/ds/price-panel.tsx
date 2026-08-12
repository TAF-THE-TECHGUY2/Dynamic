import { useForm } from '@inertiajs/react';

import type { FormEvent } from 'react';
import { decimalInput } from '@/lib/money';

export type PricingGrade = {
    id: string;
    label: string;
    blurb: string | null;
    price_per_litre: string | null;
};

export type PricingProps = {
    grades: PricingGrade[];
    lastChangedAt: string | null;
    canPublish: boolean;
};

/**
 * The dark price panel from the mockup.
 *
 * Publishing appends a row to price_history; it never updates one in place, so
 * a quote issued last week can always be explained. It also flushes the public
 * page cache, so the homepage shows the new figure immediately.
 */
export default function PricePanel({
    grades,
    lastChangedAt,
    canPublish,
}: PricingProps) {
    const form = useForm<{ prices: Record<string, string> }>({
        prices: Object.fromEntries(
            grades.map((grade) => [
                grade.id,
                decimalInput(grade.price_per_litre, 2),
            ]),
        ),
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post('/admin/pricing', { preserveScroll: true });
    }

    return (
        <form className="ds-priceset" onSubmit={submit}>
            <div className="ph3">Today's diesel price</div>
            <p className="note">
                Change it here and it updates the website, the quote calculator
                and every new invoice. Quotes already issued keep the price they
                were issued at.
            </p>

            {grades.map((grade) => (
                <div className="g" key={grade.id}>
                    <div className="gh">
                        <div className="gn">{grade.id.toUpperCase()}</div>
                        <div className="gu">
                            {(grade.blurb ?? '').toUpperCase()}
                        </div>
                    </div>
                    <div className="ds-pinput">
                        <div className="cur">R</div>
                        <input
                            inputMode="decimal"
                            aria-label={`${grade.label} price per litre`}
                            disabled={!canPublish}
                            value={form.data.prices[grade.id] ?? ''}
                            onChange={(event) =>
                                form.setData('prices', {
                                    ...form.data.prices,
                                    [grade.id]: event.target.value,
                                })
                            }
                        />
                        <div className="per">PER L</div>
                    </div>
                    {form.errors[
                        `prices.${grade.id}` as keyof typeof form.errors
                    ] && (
                        <div
                            style={{
                                color: 'var(--color-ds-red)',
                                fontSize: 11,
                                marginTop: 6,
                                fontWeight: 600,
                            }}
                        >
                            {
                                form.errors[
                                    `prices.${grade.id}` as keyof typeof form.errors
                                ]
                            }
                        </div>
                    )}
                </div>
            ))}

            {canPublish ? (
                <button
                    type="submit"
                    className="ds-btn ds-btn-red save"
                    disabled={form.processing}
                >
                    {form.processing ? 'Publishing…' : 'Publish new price'}
                </button>
            ) : (
                <p className="note" style={{ margin: '16px 0 0' }}>
                    Only the owner can publish a price.
                </p>
            )}

            <div className="last">
                {lastChangedAt
                    ? `LAST CHANGED ${formatStamp(lastChangedAt)}`
                    : 'NO PRICE PUBLISHED YET'}
            </div>
        </form>
    );
}

function formatStamp(iso: string): string {
    const date = new Date(iso);

    return date
        .toLocaleString('en-ZA', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            hour12: false,
        })
        .toUpperCase();
}
