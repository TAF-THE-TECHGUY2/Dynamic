import { Link } from '@inertiajs/react';

export type DocumentRow = {
    id: string;
    number: string;
    doc_type: string;
    doc_type_label: string;
    company: string;
    litres_display: string;
    total_display: string;
    issued_display: string;
    status: string;
    status_label: string;
    pill: string;
    is_expired: boolean;
    is_overdue: boolean;
    public_url: string | null;
};

/**
 * The recent-activity rows on the dashboard: customer, litres, value, status.
 */
export default function DocumentRows({
    rows,
    emptyMessage,
    columns,
}: {
    rows: DocumentRow[];
    emptyMessage: string;
    columns: number;
}) {
    if (rows.length === 0) {
        return (
            <tr className="empty">
                <td colSpan={columns}>{emptyMessage}</td>
            </tr>
        );
    }

    return (
        <>
            {rows.map((row) => (
                <tr key={row.id}>
                    <td>
                        <Link
                            href={`/admin/documents/${row.id}/edit`}
                            style={{ textDecoration: 'none', color: 'inherit' }}
                        >
                            <div className="ds-cust">{row.company}</div>
                            <div className="ds-qref">
                                {row.number} · {row.doc_type_label}
                            </div>
                        </Link>
                    </td>
                    <td>{row.litres_display} L</td>
                    <td>{row.total_display}</td>
                    <td>
                        <span className={`ds-pill ${row.pill}`}>
                            {row.status_label}
                        </span>
                    </td>
                </tr>
            ))}
        </>
    );
}
