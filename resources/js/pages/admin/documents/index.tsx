import { Link, router } from '@inertiajs/react';
import { useState } from 'react';

import type { DocumentRow } from '@/components/ds/document-rows';
import MetricCard from '@/components/ds/metric-card';
import DsAdminLayout from '@/layouts/ds-admin-layout';

type Paginated<T> = {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    from: number | null;
    to: number | null;
    total: number;
};

type Props = {
    documents: Paginated<DocumentRow>;
    filters: { type?: string; status?: string; search?: string };
    metrics: {
        awaiting_display: string;
        awaiting_count: number;
        overdue_display: string;
        overdue_count: number;
        expiring_today: number;
    };
};

export default function DocumentsIndex({ documents, filters, metrics }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');

    function applyFilter(next: Partial<Props['filters']>) {
        router.get(
            '/admin/documents',
            { ...filters, ...next },
            { preserveState: true, replace: true },
        );
    }

    return (
        <DsAdminLayout
            title="Invoices & quotes"
            breadcrumb={
                filters.type === 'invoice'
                    ? 'Invoices'
                    : filters.type === 'quote'
                      ? 'Quotes'
                      : 'Documents'
            }
            heading="INVOICES &amp; QUOTES"
            actions={
                <Link
                    className="ds-btn ds-btn-red"
                    href="/admin/documents/create"
                >
                    + New document
                </Link>
            }
        >
            <div className="ds-cards ds-cards-3">
                <MetricCard
                    label="Awaiting payment"
                    value={metrics.awaiting_display}
                    detail={`${metrics.awaiting_count} invoice${metrics.awaiting_count === 1 ? '' : 's'}`}
                />
                <MetricCard
                    label="Overdue"
                    value={metrics.overdue_display}
                    detail={
                        metrics.overdue_count > 0
                            ? `${metrics.overdue_count} invoice${metrics.overdue_count === 1 ? '' : 's'} — chase these`
                            : 'Nothing overdue'
                    }
                    detailTone={metrics.overdue_count > 0 ? 'dn' : 'flat'}
                />
                <MetricCard
                    label="Quotes expiring today"
                    value={String(metrics.expiring_today)}
                    detail="Diesel reprices monthly"
                />
            </div>

            <div className="ds-pane" style={{ marginTop: 16 }}>
                <div className="ph">
                    <h3>All documents</h3>

                    <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                        <input
                            aria-label="Search documents"
                            placeholder="Search number or company"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            onKeyDown={(event) =>
                                event.key === 'Enter' && applyFilter({ search })
                            }
                            style={filterStyle}
                        />
                        <select
                            aria-label="Filter by type"
                            value={filters.type ?? ''}
                            onChange={(event) =>
                                applyFilter({
                                    type: event.target.value || undefined,
                                })
                            }
                            style={filterStyle}
                        >
                            <option value="">All types</option>
                            <option value="quote">Quotes</option>
                            <option value="proforma">Pro formas</option>
                            <option value="invoice">Invoices</option>
                        </select>
                        <select
                            aria-label="Filter by status"
                            value={filters.status ?? ''}
                            onChange={(event) =>
                                applyFilter({
                                    status: event.target.value || undefined,
                                })
                            }
                            style={filterStyle}
                        >
                            <option value="">All statuses</option>
                            <option value="draft">Draft</option>
                            <option value="sent">Sent</option>
                            <option value="accepted">Accepted</option>
                            <option value="paid">Paid</option>
                            <option value="expired">Expired</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>

                <table className="ds-tbl">
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Type</th>
                            <th>Litres</th>
                            <th>Total</th>
                            <th>Issued</th>
                            <th>Status</th>
                            <th />
                        </tr>
                    </thead>
                    <tbody>
                        {documents.data.length === 0 ? (
                            <tr className="empty">
                                <td colSpan={7}>
                                    Nothing here yet. Build your first quote.
                                </td>
                            </tr>
                        ) : (
                            documents.data.map((row) => (
                                <tr key={row.id}>
                                    <td>
                                        <div className="ds-cust">
                                            {row.company}
                                        </div>
                                        <div className="ds-qref">
                                            {row.number}
                                        </div>
                                    </td>
                                    <td>{row.doc_type_label}</td>
                                    <td>{row.litres_display} L</td>
                                    <td>{row.total_display}</td>
                                    <td>{row.issued_display}</td>
                                    <td>
                                        <span className={`ds-pill ${row.pill}`}>
                                            {row.status_label}
                                        </span>
                                    </td>
                                    <td>
                                        {row.is_expired &&
                                        row.doc_type === 'quote' ? (
                                            <button
                                                type="button"
                                                className="ds-linkred"
                                                onClick={() =>
                                                    router.post(
                                                        `/admin/documents/${row.id}/reprice`,
                                                    )
                                                }
                                            >
                                                Re-price
                                            </button>
                                        ) : (
                                            <Link
                                                className="ds-linkred"
                                                href={`/admin/documents/${row.id}/edit`}
                                            >
                                                {row.is_overdue
                                                    ? 'Chase'
                                                    : 'Open'}
                                            </Link>
                                        )}
                                    </td>
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>

                {documents.links.length > 3 && (
                    <div
                        style={{
                            display: 'flex',
                            gap: 6,
                            padding: '14px 18px',
                            borderTop: '1px solid var(--color-ds-line)',
                            flexWrap: 'wrap',
                        }}
                    >
                        {documents.links.map((link, index) => (
                            <Link
                                key={index}
                                href={link.url ?? '#'}
                                className="ds-num"
                                style={{
                                    fontSize: 11,
                                    padding: '6px 10px',
                                    borderRadius: 3,
                                    textDecoration: 'none',
                                    border: '1px solid var(--color-ds-line)',
                                    background: link.active
                                        ? 'var(--color-ds-ink)'
                                        : '#fff',
                                    color: link.active
                                        ? '#fff'
                                        : 'var(--color-ds-steel)',
                                    pointerEvents: link.url ? 'auto' : 'none',
                                    opacity: link.url ? 1 : 0.4,
                                }}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </div>
                )}
            </div>
        </DsAdminLayout>
    );
}

const filterStyle: React.CSSProperties = {
    fontSize: 12,
    padding: '7px 9px',
    border: '1px solid var(--color-ds-line)',
    borderRadius: 3,
    fontFamily: 'var(--font-body)',
};
