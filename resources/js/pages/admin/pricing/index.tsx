import PricePanel from '@/components/ds/price-panel';
import type { PricingProps } from '@/components/ds/price-panel';
import DsAdminLayout from '@/layouts/ds-admin-layout';
import { money } from '@/lib/money';

type HistoryRow = {
    id: string;
    grade: string;
    price_per_litre: string;
    vat_inclusive: boolean;
    effective_from: string;
    published_by: string | null;
};

type Props = PricingProps & { history: HistoryRow[] };

export default function PricingIndex({
    grades,
    lastChangedAt,
    canPublish,
    history,
}: Props) {
    return (
        <DsAdminLayout
            title="Diesel pricing"
            breadcrumb="Diesel pricing"
            heading="DIESEL PRICING"
        >
            <div className="ds-two">
                <div className="ds-pane">
                    <div className="ph">
                        <h3>Price history</h3>
                        <span
                            className="ds-num"
                            style={{
                                fontSize: 9.5,
                                color: 'var(--color-ds-mute)',
                                letterSpacing: '.05em',
                            }}
                        >
                            APPEND ONLY
                        </span>
                    </div>

                    <table className="ds-tbl">
                        <thead>
                            <tr>
                                <th>Grade</th>
                                <th>Price / L</th>
                                <th>VAT</th>
                                <th>Effective from</th>
                                <th>Published by</th>
                            </tr>
                        </thead>
                        <tbody>
                            {history.length === 0 ? (
                                <tr className="empty">
                                    <td colSpan={5}>
                                        No price has been published yet. Set one
                                        on the right.
                                    </td>
                                </tr>
                            ) : (
                                history.map((row) => (
                                    <tr key={row.id}>
                                        <td>
                                            <div className="ds-cust">
                                                {row.grade}
                                            </div>
                                        </td>
                                        <td>
                                            {money(
                                                Math.round(
                                                    Number(
                                                        row.price_per_litre,
                                                    ) * 100,
                                                ),
                                            )}
                                        </td>
                                        <td>
                                            {row.vat_inclusive
                                                ? 'Incl.'
                                                : 'Excl.'}
                                        </td>
                                        <td>
                                            {formatStamp(row.effective_from)}
                                        </td>
                                        <td>{row.published_by ?? '—'}</td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>

                    <div
                        style={{
                            padding: '14px 18px',
                            borderTop: '1px solid var(--color-ds-line)',
                            fontSize: 11,
                            lineHeight: 1.62,
                            color: 'var(--color-ds-steel)',
                        }}
                    >
                        Nothing here is ever edited or deleted. A new price is a
                        new row, which is what makes it possible to answer{' '}
                        <em>“why does this quote say R23.70?”</em> six months
                        from now.
                    </div>
                </div>

                <PricePanel
                    grades={grades}
                    lastChangedAt={lastChangedAt}
                    canPublish={canPublish}
                />
            </div>
        </DsAdminLayout>
    );
}

function formatStamp(iso: string): string {
    return new Date(iso).toLocaleString('en-ZA', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    });
}
