import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';

import DsAdminLayout from '@/layouts/ds-admin-layout';

type Customer = {
    id: string;
    company: string;
    contact_name: string | null;
    phone: string | null;
    email: string | null;
    vat_number: string | null;
    delivery_address: string | null;
    notes: string | null;
    documents_count: number;
    billed_display: string;
};

type Props = {
    customers: { data: Customer[]; total: number };
    filters: { search: string };
};

const BLANK = {
    company: '',
    contact_name: '',
    phone: '',
    email: '',
    vat_number: '',
    delivery_address: '',
    notes: '',
};

export default function CustomersIndex({ customers, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [editing, setEditing] = useState<Customer | null>(null);

    const form = useForm({ ...BLANK });

    function startEdit(customer: Customer) {
        setEditing(customer);
        form.setData({
            company: customer.company,
            contact_name: customer.contact_name ?? '',
            phone: customer.phone ?? '',
            email: customer.email ?? '',
            vat_number: customer.vat_number ?? '',
            delivery_address: customer.delivery_address ?? '',
            notes: customer.notes ?? '',
        });
    }

    function submit(event: FormEvent) {
        event.preventDefault();

        const done = {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setEditing(null);
            },
        };

        if (editing) {
            form.patch(`/admin/customers/${editing.id}`, done);
        } else {
            form.post('/admin/customers', done);
        }
    }

    return (
        <DsAdminLayout
            title="Customers"
            breadcrumb="Customers"
            heading="CUSTOMERS"
        >
            <div className="ds-two">
                <div className="ds-pane">
                    <div className="ph">
                        <h3>All customers ({customers.total})</h3>
                        <input
                            aria-label="Search customers"
                            placeholder="Search company or contact"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            onKeyDown={(event) =>
                                event.key === 'Enter' &&
                                router.get(
                                    '/admin/customers',
                                    { search },
                                    { preserveState: true },
                                )
                            }
                            style={{
                                fontSize: 12,
                                padding: '7px 9px',
                                border: '1px solid var(--color-ds-line)',
                                borderRadius: 3,
                            }}
                        />
                    </div>

                    <table className="ds-tbl">
                        <thead>
                            <tr>
                                <th>Company</th>
                                <th>Mobile</th>
                                <th>Docs</th>
                                <th>Billed</th>
                                <th />
                            </tr>
                        </thead>
                        <tbody>
                            {customers.data.length === 0 ? (
                                <tr className="empty">
                                    <td colSpan={5}>No customers yet.</td>
                                </tr>
                            ) : (
                                customers.data.map((customer) => (
                                    <tr key={customer.id}>
                                        <td>
                                            <div className="ds-cust">
                                                {customer.company}
                                            </div>
                                            <div className="ds-qref">
                                                {customer.contact_name ?? '—'}
                                            </div>
                                        </td>
                                        <td>{customer.phone ?? '—'}</td>
                                        <td>{customer.documents_count}</td>
                                        <td>{customer.billed_display}</td>
                                        <td>
                                            <button
                                                type="button"
                                                className="ds-linkred"
                                                onClick={() =>
                                                    startEdit(customer)
                                                }
                                            >
                                                Edit
                                            </button>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                <form className="ds-pane" onSubmit={submit}>
                    <div className="ph">
                        <h3>{editing ? 'Edit customer' : 'Add a customer'}</h3>
                        {editing && (
                            <button
                                type="button"
                                className="ds-linkred"
                                onClick={() => {
                                    setEditing(null);
                                    form.reset();
                                }}
                            >
                                Cancel
                            </button>
                        )}
                    </div>

                    <div className="pb" style={{ paddingTop: 4 }}>
                        <Field label="Company" error={form.errors.company}>
                            <input
                                value={form.data.company}
                                onChange={(event) =>
                                    form.setData('company', event.target.value)
                                }
                                required
                            />
                        </Field>

                        <div className="ds-g2">
                            <Field
                                label="Contact"
                                error={form.errors.contact_name}
                            >
                                <input
                                    value={form.data.contact_name}
                                    onChange={(event) =>
                                        form.setData(
                                            'contact_name',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                            <Field label="Mobile" error={form.errors.phone}>
                                <input
                                    className="mono"
                                    value={form.data.phone}
                                    onChange={(event) =>
                                        form.setData(
                                            'phone',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                        </div>

                        <div className="ds-g2">
                            <Field label="Email" error={form.errors.email}>
                                <input
                                    type="email"
                                    value={form.data.email}
                                    onChange={(event) =>
                                        form.setData(
                                            'email',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                            <Field
                                label="VAT number"
                                error={form.errors.vat_number}
                            >
                                <input
                                    className="mono"
                                    value={form.data.vat_number}
                                    onChange={(event) =>
                                        form.setData(
                                            'vat_number',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                        </div>

                        <Field
                            label="Delivery address"
                            error={form.errors.delivery_address}
                        >
                            <textarea
                                rows={2}
                                value={form.data.delivery_address}
                                onChange={(event) =>
                                    form.setData(
                                        'delivery_address',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>

                        <Field label="Notes" error={form.errors.notes}>
                            <textarea
                                rows={2}
                                value={form.data.notes}
                                onChange={(event) =>
                                    form.setData('notes', event.target.value)
                                }
                            />
                        </Field>

                        <button
                            type="submit"
                            className="ds-btn ds-btn-red"
                            style={{ width: '100%', marginTop: 14 }}
                            disabled={form.processing}
                        >
                            {editing ? 'Save changes' : 'Add customer'}
                        </button>

                        {editing && (
                            <p
                                style={{
                                    fontSize: 11,
                                    color: 'var(--color-ds-mute)',
                                    lineHeight: 1.62,
                                    margin: '12px 0 0',
                                }}
                            >
                                Editing a customer does not touch documents
                                already issued — those carry their own snapshot
                                of the details as they were at the time.
                            </p>
                        )}
                    </div>
                </form>
            </div>
        </DsAdminLayout>
    );
}

function Field({
    label,
    error,
    children,
}: {
    label: string;
    error?: string;
    children: React.ReactNode;
}) {
    return (
        <div className="ds-fld">
            <label>{label}</label>
            {children}
            {error && <div className="err">{error}</div>}
        </div>
    );
}
