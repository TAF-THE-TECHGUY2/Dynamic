import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import DsAdminLayout from '@/layouts/ds-admin-layout';

type Values = Record<string, string | number | null>;

type Props = {
    values: Values;
    vatRegistered: boolean;
};

const GROUPS: {
    title: string;
    note?: string;
    fields: [string, string, 'text' | 'area' | 'number'][];
}[] = [
    {
        title: 'Business',
        note: 'Printed at the top right of every quote and invoice.',
        fields: [
            ['business.trading_name', 'Trading name', 'text'],
            ['business.registered_name', 'Registered name', 'text'],
            ['business.reg_number', 'Registration number', 'text'],
            ['business.vat_number', 'VAT number', 'text'],
            ['business.email', 'Email', 'text'],
            ['business.phone', 'Phone', 'text'],
            [
                'business.whatsapp',
                'WhatsApp (international, digits only)',
                'text',
            ],
            ['business.address_line1', 'Address line 1', 'text'],
            ['business.address_line2', 'Address line 2', 'text'],
            ['business.address_line3', 'Address line 3', 'text'],
            ['business.hours_weekday', 'Hours — weekdays', 'text'],
            ['business.hours_saturday', 'Hours — Saturday', 'text'],
            ['business.hours_note', 'Hours — note', 'text'],
        ],
    },
    {
        title: 'Banking',
        note: 'Snapshotted onto each document when it is created. Changing it here does not alter documents already issued.',
        fields: [
            ['banking.bank', 'Bank', 'text'],
            ['banking.account_name', 'Account name', 'text'],
            ['banking.account_number', 'Account number', 'text'],
            ['banking.branch_code', 'Branch code', 'text'],
            ['banking.account_type', 'Account type', 'text'],
            ['banking.reference_note', 'Reference note', 'text'],
        ],
    },
    {
        title: 'Trading rules',
        fields: [
            ['vat_rate', 'VAT rate (0.15 = 15%)', 'text'],
            ['quote_valid_hours', 'Quote validity (hours)', 'number'],
            ['invoice_due_days', 'Invoice payment terms (days)', 'number'],
            ['min_order_litres', 'Minimum delivery order (litres)', 'number'],
            ['delivery_note', 'Delivery note', 'text'],
            ['yard.bays', 'Yard capacity (bays)', 'number'],
            ['yard.rate_per_day', 'Yard rate per day', 'text'],
        ],
    },
    {
        title: 'Terms',
        note: 'Copied onto each document at creation, so wording changes never rewrite history.',
        fields: [
            ['terms.quote', 'Quote terms', 'area'],
            ['terms.proforma', 'Pro forma terms', 'area'],
            ['terms.invoice', 'Invoice terms', 'area'],
        ],
    },
];

export default function SettingsIndex({ values, vatRegistered }: Props) {
    const form = useForm<Values>({ ...values });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.patch('/admin/settings', { preserveScroll: true });
    }

    const todos = Object.entries(values).filter(
        ([key, value]) =>
            (typeof value === 'string' && value.trim().startsWith('TODO')) ||
            (key === 'yard.bays' && Number(value) <= 0),
    );

    return (
        <DsAdminLayout
            title="Settings"
            breadcrumb="Settings"
            heading="SETTINGS"
        >
            {todos.length > 0 && (
                <div className="ds-banner" style={{ marginBottom: 16 }}>
                    <b>
                        {todos.length} setting{todos.length === 1 ? '' : 's'}{' '}
                        still need filling in
                    </b>
                    These are printed on documents customers receive. Anything
                    still reading “TODO” will appear on a quote exactly as
                    written.
                </div>
            )}

            {!vatRegistered && (
                <div className="ds-banner warn" style={{ marginBottom: 16 }}>
                    <b>No VAT number configured</b>
                    Every document is being issued with no VAT line at all. That
                    is the correct behaviour when you are not VAT registered —
                    showing VAT without a VAT number is not compliant with SARS
                    requirements. Add one below to enable VAT.
                </div>
            )}

            <form onSubmit={submit}>
                <div
                    className="ds-two"
                    style={{
                        gridTemplateColumns: '1fr 1fr',
                        alignItems: 'start',
                    }}
                >
                    {GROUPS.map((group) => (
                        <div
                            className="ds-pane"
                            key={group.title}
                            style={{ marginBottom: 16 }}
                        >
                            <div className="ph">
                                <h3>{group.title}</h3>
                            </div>
                            <div className="pb" style={{ paddingTop: 4 }}>
                                {group.note && (
                                    <p
                                        style={{
                                            fontSize: 11,
                                            color: 'var(--color-ds-mute)',
                                            lineHeight: 1.62,
                                            margin: '6px 0 0',
                                        }}
                                    >
                                        {group.note}
                                    </p>
                                )}

                                {group.fields.map(([key, label, kind]) => (
                                    <div className="ds-fld" key={key}>
                                        <label htmlFor={key}>{label}</label>

                                        {kind === 'area' ? (
                                            <textarea
                                                id={key}
                                                rows={5}
                                                value={String(
                                                    form.data[key] ?? '',
                                                )}
                                                onChange={(event) =>
                                                    form.setData(
                                                        key,
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                        ) : (
                                            <input
                                                id={key}
                                                className={
                                                    kind === 'number'
                                                        ? 'mono'
                                                        : undefined
                                                }
                                                inputMode={
                                                    kind === 'number'
                                                        ? 'numeric'
                                                        : undefined
                                                }
                                                value={String(
                                                    form.data[key] ?? '',
                                                )}
                                                onChange={(event) =>
                                                    form.setData(
                                                        key,
                                                        kind === 'number'
                                                            ? event.target.value.replace(
                                                                  /\D/g,
                                                                  '',
                                                              )
                                                            : event.target
                                                                  .value,
                                                    )
                                                }
                                            />
                                        )}

                                        {form.errors[
                                            key as keyof typeof form.errors
                                        ] && (
                                            <div className="err">
                                                {
                                                    form.errors[
                                                        key as keyof typeof form.errors
                                                    ]
                                                }
                                            </div>
                                        )}
                                    </div>
                                ))}
                            </div>
                        </div>
                    ))}
                </div>

                <button
                    type="submit"
                    className="ds-btn ds-btn-red"
                    disabled={form.processing}
                >
                    {form.processing ? 'Saving…' : 'Save settings'}
                </button>
            </form>
        </DsAdminLayout>
    );
}
