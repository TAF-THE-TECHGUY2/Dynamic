import { Link } from '@inertiajs/react';

import DocumentRows from '@/components/ds/document-rows';
import type { DocumentRow } from '@/components/ds/document-rows';
import MetricCard from '@/components/ds/metric-card';
import PricePanel from '@/components/ds/price-panel';
import type { PricingProps } from '@/components/ds/price-panel';
import DsAdminLayout from '@/layouts/ds-admin-layout';

type Metric = {
    value: string;
    delta?: { percent: number; direction: 'up' | 'dn' | 'flat' } | null;
    detail?: string;
    previous_label?: string;
};

type Props = {
    greeting: string;
    metrics: {
        litres: Metric;
        revenue: Metric;
        open_quotes: Metric;
        overdue: Metric;
        month_label: string;
    };
    recent: DocumentRow[];
    pricing: PricingProps;
    yard: {
        bays: number;
        occupied: number;
        free: number;
        checkingOutToday: number;
    };
};

export default function Dashboard({
    greeting,
    metrics,
    recent,
    pricing,
    yard,
}: Props) {
    const occupancy =
        yard.bays > 0
            ? Math.min(100, Math.round((yard.occupied / yard.bays) * 100))
            : 0;

    return (
        <DsAdminLayout
            title="Dashboard"
            breadcrumb="Dashboard"
            heading={greeting}
            actions={
                <Link
                    className="ds-btn ds-btn-red"
                    href="/admin/documents/create?type=quote"
                >
                    + New quote
                </Link>
            }
        >
            <div className="ds-cards">
                <MetricCard
                    label={`Litres sold · ${metrics.month_label}`}
                    value={metrics.litres.value}
                    delta={metrics.litres.delta}
                    previousLabel={metrics.litres.previous_label}
                />
                <MetricCard
                    label={`Revenue · ${metrics.month_label}`}
                    value={metrics.revenue.value}
                    delta={metrics.revenue.delta}
                    previousLabel={metrics.revenue.previous_label}
                />
                <MetricCard
                    label="Quotes open"
                    value={metrics.open_quotes.value}
                    detail={metrics.open_quotes.detail}
                />
                {/* Overdue renders red — it is the number that actually costs money. */}
                <MetricCard
                    label="Overdue invoices"
                    value={metrics.overdue.value}
                    detail={metrics.overdue.detail}
                    detailTone="dn"
                />
            </div>

            <div className="ds-two">
                <div className="ds-pane">
                    <div className="ph">
                        <h3>Recent activity</h3>
                        <Link className="ds-linkred" href="/admin/documents">
                            View all →
                        </Link>
                    </div>

                    <table className="ds-tbl">
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Litres</th>
                                <th>Value</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <DocumentRows
                                rows={recent}
                                emptyMessage="No quotes or invoices yet."
                                columns={4}
                            />
                        </tbody>
                    </table>
                </div>

                <div>
                    <PricePanel {...pricing} />

                    <div className="ds-pane" style={{ marginTop: 16 }}>
                        <div className="ph">
                            <h3>Yard occupancy</h3>
                            <Link className="ds-linkred" href="/admin/yard">
                                Open →
                            </Link>
                        </div>
                        <div className="pb">
                            <div
                                style={{
                                    display: 'flex',
                                    justifyContent: 'space-between',
                                    alignItems: 'baseline',
                                }}
                            >
                                <div
                                    className="ds-num"
                                    style={{ fontWeight: 700, fontSize: 26 }}
                                >
                                    {yard.occupied}
                                    <span
                                        style={{
                                            fontSize: 14,
                                            color: 'var(--color-ds-mute)',
                                        }}
                                    >
                                        {' '}
                                        / {yard.bays}
                                    </span>
                                </div>
                                <div
                                    style={{
                                        fontSize: 11,
                                        fontWeight: 600,
                                        color: 'var(--color-ds-steel)',
                                    }}
                                >
                                    bays taken
                                </div>
                            </div>

                            <div
                                style={{
                                    height: 8,
                                    background: 'var(--color-ds-mist)',
                                    borderRadius: 2,
                                    marginTop: 12,
                                    overflow: 'hidden',
                                }}
                            >
                                <div
                                    style={{
                                        width: `${occupancy}%`,
                                        height: '100%',
                                        background: 'var(--color-ds-red)',
                                    }}
                                />
                            </div>

                            <div
                                className="ds-num"
                                style={{
                                    fontSize: 10,
                                    color: 'var(--color-ds-mute)',
                                    marginTop: 10,
                                }}
                            >
                                {yard.free} BAYS FREE · {yard.checkingOutToday}{' '}
                                CHECKING OUT TODAY
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </DsAdminLayout>
    );
}
