import { Link, router, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';

import DocumentPaper from '@/components/ds/document-paper';
import type { BusinessBlock } from '@/components/ds/document-paper';
import DsAdminLayout from '@/layouts/ds-admin-layout';
import { decimalInput } from '@/lib/money';
import { computeTotals } from '@/lib/totals';
import type { VatMode } from '@/lib/totals';

type Grade = {
    id: string;
    label: string;
    code: string;
    price_per_litre: string | null;
};

type CustomerOption = {
    id: string;
    company: string;
    contact_name: string | null;
    phone: string | null;
    email: string | null;
    vat_number: string | null;
    delivery_address: string | null;
};

type LineForm = {
    grade_id: string | null;
    code: string | null;
    description: string;
    litres: string | null;
    price_per_litre: string | null;
};

type ExistingDocument = {
    id: string;
    number: string;
    doc_type: string;
    status: string;
    status_label: string;
    customer_id: string | null;
    company: string;
    contact_name: string;
    phone: string;
    email: string;
    vat_number: string;
    delivery_address: string;
    issued_on: string;
    valid_until: string | null;
    due_on: string | null;
    fulfilment: string;
    vat_mode: VatMode;
    vat_rate: string;
    notes: string;
    terms: string;
    banking: string;
    subtotal_cents: number;
    lines: LineForm[];
    is_expired: boolean;
    public_url: string | null;
    converted_from: string | null;
};

type Props = {
    document: ExistingDocument | null;
    placeholderNumber: string;
    defaultType: string;
    grades: Grade[];
    customers: CustomerOption[];
    business: BusinessBlock;
    terms: Record<string, string>;
    banking: string;
    vatRegistered: boolean;
    vatRate: string;
    defaultVatMode: VatMode;
    quoteValidHours: number;
    invoiceDueDays: number;
};

const TYPE_LABELS: Record<string, string> = {
    quote: 'Quote',
    proforma: 'Pro forma',
    invoice: 'Tax invoice',
};

const TYPE_TITLES: Record<string, string> = {
    quote: 'QUOTE',
    proforma: 'PRO FORMA',
    invoice: 'TAX INVOICE',
};

export default function Builder(props: Props) {
    const {
        document: existing,
        placeholderNumber,
        defaultType,
        grades,
        customers,
        business,
        terms,
        banking,
        vatRegistered,
        vatRate,
        defaultVatMode,
        quoteValidHours,
        invoiceDueDays,
    } = props;

    const today = new Date().toISOString().slice(0, 10);

    const form = useForm({
        doc_type: existing?.doc_type ?? defaultType,
        customer_id: existing?.customer_id ?? null,
        company: existing?.company ?? '',
        contact_name: existing?.contact_name ?? '',
        phone: existing?.phone ?? '',
        email: existing?.email ?? '',
        vat_number: existing?.vat_number ?? '',
        delivery_address: existing?.delivery_address ?? '',
        issued_on: existing?.issued_on ?? today,
        valid_until: existing?.valid_until ?? addHours(today, quoteValidHours),
        due_on: existing?.due_on ?? addDays(today, invoiceDueDays),
        fulfilment: existing?.fulfilment ?? 'delivery',
        vat_mode: (existing?.vat_mode ?? defaultVatMode) as VatMode,
        notes: existing?.notes ?? '',
        lines: existing ? existing.lines.map(tidyLine) : [defaultLine(grades)],
        autosave: false,
    });

    const { data, setData } = form;

    /**
     * The live preview.
     *
     * resources/js/lib/totals.ts is a line-for-line port of App\Support\Totals,
     * and tests/fixtures/totals.json is read by both suites so they cannot
     * drift. These figures are never posted — the server recomputes on save and
     * the stored value wins.
     */
    const totals = useMemo(
        () =>
            computeTotals(
                data.lines.map((line) => ({
                    litres: line.litres,
                    price_per_litre: line.price_per_litre,
                })),
                vatRegistered ? data.vat_mode : 'none',
                vatRate,
            ),
        [data.lines, data.vat_mode, vatRegistered, vatRate],
    );

    const [savedAt, setSavedAt] = useState<string | null>(null);
    const autosaveTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
    const firstRender = useRef(true);

    // Autosave two seconds after typing stops. Nobody should lose a half-built
    // quote to a closed tab.
    useEffect(() => {
        if (existing === null) {
            return;
        }

        if (firstRender.current) {
            firstRender.current = false;

            return;
        }

        if (autosaveTimer.current) {
            clearTimeout(autosaveTimer.current);
        }

        autosaveTimer.current = setTimeout(() => {
            router.patch(
                `/admin/documents/${existing.id}`,
                { ...data, autosave: true },
                {
                    preserveScroll: true,
                    preserveState: true,
                    only: [],
                    onSuccess: () =>
                        setSavedAt(
                            new Date().toLocaleTimeString('en-ZA', {
                                hour12: false,
                            }),
                        ),
                },
            );
        }, 2000);

        return () => {
            if (autosaveTimer.current) {
                clearTimeout(autosaveTimer.current);
            }
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [data]);

    function updateLine(index: number, patch: Partial<LineForm>) {
        setData(
            'lines',
            data.lines.map((line, i) =>
                i === index ? { ...line, ...patch } : line,
            ),
        );
    }

    function pickGrade(index: number, gradeId: string) {
        const grade = grades.find((candidate) => candidate.id === gradeId);

        if (!grade) {
            return;
        }

        updateLine(index, {
            grade_id: grade.id,
            code: grade.code,
            description: grade.label,
            // Defaults to today's price but stays editable, so a fleet discount
            // does not mean fighting the system.
            price_per_litre: decimalInput(grade.price_per_litre, 2),
        });
    }

    function pickCustomer(id: string) {
        const customer = customers.find((candidate) => candidate.id === id);

        if (!customer) {
            setData('customer_id', null);

            return;
        }

        setData((current) => ({
            ...current,
            customer_id: customer.id,
            company: customer.company,
            contact_name: customer.contact_name ?? '',
            phone: customer.phone ?? '',
            email: customer.email ?? '',
            vat_number: customer.vat_number ?? '',
            delivery_address: customer.delivery_address ?? '',
        }));
    }

    function save() {
        // An explicit save should say so — only the debounced autosave stays quiet.
        form.transform((payload) => ({ ...payload, autosave: false }));

        if (existing) {
            form.patch(`/admin/documents/${existing.id}`, {
                preserveScroll: true,
            });
        } else {
            form.post('/admin/documents');
        }
    }

    const isQuote = data.doc_type === 'quote';
    const todaysPrice = grades[0]?.price_per_litre;

    return (
        <DsAdminLayout
            title={existing ? existing.number : 'New document'}
            breadcrumb={`${TYPE_LABELS[data.doc_type] ?? 'Document'} / ${existing ? 'Edit' : 'New'}`}
            heading={
                existing
                    ? `EDIT ${TYPE_LABELS[data.doc_type].toUpperCase()}`
                    : `BUILD A ${TYPE_LABELS[data.doc_type].toUpperCase()}`
            }
            actions={
                <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                    {savedAt && (
                        <span
                            className="ds-num"
                            style={{
                                fontSize: 10,
                                color: 'var(--color-ds-mute)',
                            }}
                        >
                            AUTOSAVED {savedAt}
                        </span>
                    )}
                    <span
                        className="ds-num"
                        style={{ fontSize: 12, color: 'var(--color-ds-steel)' }}
                    >
                        {existing?.number ?? placeholderNumber}
                    </span>
                </div>
            }
        >
            {existing?.is_expired && (
                <div className="ds-banner" style={{ marginBottom: 14 }}>
                    <b>This quote has expired</b>
                    It was valid until {existing.valid_until}. Re-price it at
                    today's rate — the original stays exactly as it was issued.
                    <br />
                    <button
                        type="button"
                        className="ds-linkred"
                        style={{ marginTop: 8 }}
                        onClick={() =>
                            router.post(
                                `/admin/documents/${existing.id}/reprice`,
                            )
                        }
                    >
                        Re-price at today's rate →
                    </button>
                </div>
            )}

            {existing?.converted_from && (
                <div className="ds-banner ok" style={{ marginBottom: 14 }}>
                    <b>Converted from {existing.converted_from}</b>
                    The line prices below are the ones the customer accepted,
                    not today's.
                </div>
            )}

            <div className="ds-build">
                <div>
                    <section className="ds-pane">
                        <div className="ph">
                            <h3>Document</h3>
                        </div>
                        <div className="pb" style={{ paddingTop: 4 }}>
                            <div className="ds-g2">
                                <div className="ds-fld">
                                    <label htmlFor="doc-type">Type</label>
                                    <select
                                        id="doc-type"
                                        value={data.doc_type}
                                        disabled={Boolean(existing)}
                                        onChange={(event) =>
                                            setData(
                                                'doc_type',
                                                event.target.value,
                                            )
                                        }
                                    >
                                        <option value="quote">Quote</option>
                                        <option value="proforma">
                                            Pro forma
                                        </option>
                                        <option value="invoice">
                                            Tax invoice
                                        </option>
                                    </select>
                                    {existing && (
                                        <div className="hint">
                                            An issued document keeps its type.
                                            Convert a quote instead.
                                        </div>
                                    )}
                                </div>
                                <div className="ds-fld">
                                    <label htmlFor="doc-number">Number</label>
                                    <input
                                        id="doc-number"
                                        className="mono"
                                        readOnly
                                        value={
                                            existing?.number ??
                                            placeholderNumber
                                        }
                                    />
                                    <div className="hint">
                                        {existing
                                            ? 'Fixed once issued.'
                                            : 'Assigned when you save.'}
                                    </div>
                                </div>
                            </div>

                            <div className="ds-g2">
                                <div className="ds-fld">
                                    <label htmlFor="issued-on">
                                        Date issued
                                    </label>
                                    <input
                                        id="issued-on"
                                        className="mono"
                                        type="date"
                                        value={data.issued_on}
                                        onChange={(event) =>
                                            setData(
                                                'issued_on',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <Err message={form.errors.issued_on} />
                                </div>
                                {isQuote ? (
                                    <div className="ds-fld">
                                        <label htmlFor="valid-until">
                                            Valid until
                                        </label>
                                        <input
                                            id="valid-until"
                                            className="mono"
                                            type="date"
                                            value={data.valid_until ?? ''}
                                            onChange={(event) =>
                                                setData(
                                                    'valid_until',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                        <div className="hint">
                                            Diesel reprices monthly —{' '}
                                            {quoteValidHours}h by default.
                                        </div>
                                        <Err
                                            message={form.errors.valid_until}
                                        />
                                    </div>
                                ) : (
                                    <div className="ds-fld">
                                        <label htmlFor="due-on">
                                            Payment due
                                        </label>
                                        <input
                                            id="due-on"
                                            className="mono"
                                            type="date"
                                            value={data.due_on ?? ''}
                                            onChange={(event) =>
                                                setData(
                                                    'due_on',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                        <Err message={form.errors.due_on} />
                                    </div>
                                )}
                            </div>
                        </div>
                    </section>

                    <section className="ds-pane" style={{ marginTop: 14 }}>
                        <div className="ph">
                            <h3>Customer</h3>
                            <select
                                aria-label="Pick a saved customer"
                                value={data.customer_id ?? ''}
                                onChange={(event) =>
                                    pickCustomer(event.target.value)
                                }
                                style={{
                                    fontSize: 12,
                                    padding: '6px 8px',
                                    border: '1px solid var(--color-ds-line)',
                                    borderRadius: 3,
                                }}
                            >
                                <option value="">Pick from saved →</option>
                                {customers.map((customer) => (
                                    <option
                                        key={customer.id}
                                        value={customer.id}
                                    >
                                        {customer.company}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="pb" style={{ paddingTop: 4 }}>
                            <div className="ds-fld">
                                <label htmlFor="company">Company</label>
                                <input
                                    id="company"
                                    value={data.company}
                                    onChange={(event) =>
                                        setData('company', event.target.value)
                                    }
                                />
                                <Err message={form.errors.company} />
                            </div>

                            <div className="ds-g2">
                                <div className="ds-fld">
                                    <label htmlFor="contact">Contact</label>
                                    <input
                                        id="contact"
                                        value={data.contact_name}
                                        onChange={(event) =>
                                            setData(
                                                'contact_name',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </div>
                                <div className="ds-fld">
                                    <label htmlFor="phone">Mobile</label>
                                    <input
                                        id="phone"
                                        className="mono"
                                        value={data.phone}
                                        onChange={(event) =>
                                            setData('phone', event.target.value)
                                        }
                                    />
                                </div>
                            </div>

                            <div className="ds-g2">
                                <div className="ds-fld">
                                    <label htmlFor="email">Email</label>
                                    <input
                                        id="email"
                                        type="email"
                                        value={data.email}
                                        onChange={(event) =>
                                            setData('email', event.target.value)
                                        }
                                    />
                                    <Err message={form.errors.email} />
                                </div>
                                <div className="ds-fld">
                                    <label htmlFor="cust-vat">
                                        Their VAT number
                                    </label>
                                    <input
                                        id="cust-vat"
                                        className="mono"
                                        value={data.vat_number}
                                        onChange={(event) =>
                                            setData(
                                                'vat_number',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </div>
                            </div>

                            <div className="ds-fld">
                                <label htmlFor="address">
                                    Delivery address
                                </label>
                                <textarea
                                    id="address"
                                    rows={2}
                                    value={data.delivery_address}
                                    onChange={(event) =>
                                        setData(
                                            'delivery_address',
                                            event.target.value,
                                        )
                                    }
                                />
                            </div>
                        </div>
                    </section>

                    <section className="ds-pane" style={{ marginTop: 14 }}>
                        <div className="ph">
                            <h3>Line items</h3>
                            {todaysPrice && (
                                <Link
                                    className="ds-linkred"
                                    href="/admin/pricing"
                                >
                                    Price today: R
                                    {Number(todaysPrice).toFixed(2)}
                                </Link>
                            )}
                        </div>
                        <div className="pb" style={{ paddingTop: 4 }}>
                            {data.lines.map((line, index) => (
                                <div className="ds-lineitem" key={index}>
                                    <div className="lh">
                                        <span>LINE {index + 1}</span>
                                        {data.lines.length > 1 && (
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    setData(
                                                        'lines',
                                                        data.lines.filter(
                                                            (_, i) =>
                                                                i !== index,
                                                        ),
                                                    )
                                                }
                                            >
                                                Remove
                                            </button>
                                        )}
                                    </div>

                                    <div className="ds-fld">
                                        <label htmlFor={`grade-${index}`}>
                                            Product
                                        </label>
                                        <select
                                            id={`grade-${index}`}
                                            value={line.grade_id ?? ''}
                                            onChange={(event) => {
                                                if (event.target.value === '') {
                                                    updateLine(index, {
                                                        grade_id: null,
                                                        code: null,
                                                        litres: null,
                                                        price_per_litre: null,
                                                    });

                                                    return;
                                                }

                                                pickGrade(
                                                    index,
                                                    event.target.value,
                                                );
                                            }}
                                        >
                                            {grades.map((grade) => (
                                                <option
                                                    key={grade.id}
                                                    value={grade.id}
                                                >
                                                    {grade.label}
                                                    {grade.price_per_litre
                                                        ? ` — R${Number(grade.price_per_litre).toFixed(2)}/L`
                                                        : ' — no price published'}
                                                </option>
                                            ))}
                                            <option value="">
                                                Other / flat charge
                                            </option>
                                        </select>
                                    </div>

                                    {line.grade_id === null ? (
                                        <div className="ds-fld">
                                            <label htmlFor={`desc-${index}`}>
                                                Description
                                            </label>
                                            <input
                                                id={`desc-${index}`}
                                                value={line.description}
                                                onChange={(event) =>
                                                    updateLine(index, {
                                                        description:
                                                            event.target.value,
                                                    })
                                                }
                                            />
                                            <div className="hint">
                                                A flat charge — delivery, a
                                                standing fee — is not multiplied
                                                out by litres.
                                            </div>
                                        </div>
                                    ) : (
                                        <div className="ds-g2">
                                            <div className="ds-fld">
                                                <label
                                                    htmlFor={`litres-${index}`}
                                                >
                                                    Litres
                                                </label>
                                                <input
                                                    id={`litres-${index}`}
                                                    className="mono"
                                                    inputMode="decimal"
                                                    value={line.litres ?? ''}
                                                    onChange={(event) =>
                                                        updateLine(index, {
                                                            litres: event.target
                                                                .value,
                                                        })
                                                    }
                                                />
                                                <Err
                                                    message={
                                                        form.errors[
                                                            `lines.${index}.litres` as keyof typeof form.errors
                                                        ]
                                                    }
                                                />
                                            </div>
                                            <div className="ds-fld">
                                                <label
                                                    htmlFor={`price-${index}`}
                                                >
                                                    Price / litre
                                                </label>
                                                <input
                                                    id={`price-${index}`}
                                                    className="mono"
                                                    inputMode="decimal"
                                                    value={
                                                        line.price_per_litre ??
                                                        ''
                                                    }
                                                    onChange={(event) =>
                                                        updateLine(index, {
                                                            price_per_litre:
                                                                event.target
                                                                    .value,
                                                        })
                                                    }
                                                />
                                                <Err
                                                    message={
                                                        form.errors[
                                                            `lines.${index}.price_per_litre` as keyof typeof form.errors
                                                        ]
                                                    }
                                                />
                                            </div>
                                        </div>
                                    )}
                                </div>
                            ))}

                            <button
                                type="button"
                                className="ds-addline"
                                onClick={() =>
                                    setData('lines', [
                                        ...data.lines,
                                        defaultLine(grades),
                                    ])
                                }
                            >
                                + Add line item
                            </button>

                            <div className="ds-g2" style={{ marginTop: 14 }}>
                                <div className="ds-fld">
                                    <label htmlFor="fulfilment">
                                        Fulfilment
                                    </label>
                                    <select
                                        id="fulfilment"
                                        value={data.fulfilment}
                                        onChange={(event) =>
                                            setData(
                                                'fulfilment',
                                                event.target.value,
                                            )
                                        }
                                    >
                                        <option value="delivery">
                                            Delivery — free (Gauteng)
                                        </option>
                                        <option value="collection">
                                            Collection at yard
                                        </option>
                                    </select>
                                </div>
                                <div className="ds-fld">
                                    <label htmlFor="vat-mode">
                                        VAT treatment
                                    </label>
                                    <select
                                        id="vat-mode"
                                        value={
                                            vatRegistered
                                                ? data.vat_mode
                                                : 'none'
                                        }
                                        disabled={!vatRegistered}
                                        onChange={(event) =>
                                            setData(
                                                'vat_mode',
                                                event.target.value as VatMode,
                                            )
                                        }
                                    >
                                        <option value="inclusive">
                                            Price includes 15% VAT
                                        </option>
                                        <option value="exclusive">
                                            Add 15% VAT
                                        </option>
                                        <option value="none">No VAT</option>
                                    </select>
                                    {!vatRegistered && (
                                        <div className="hint">
                                            No VAT number is configured, so VAT
                                            rows are hidden. Add one in Settings
                                            first.
                                        </div>
                                    )}
                                </div>
                            </div>

                            <div className="ds-fld">
                                <label htmlFor="notes">
                                    Notes on the document
                                </label>
                                <textarea
                                    id="notes"
                                    rows={2}
                                    value={data.notes}
                                    onChange={(event) =>
                                        setData('notes', event.target.value)
                                    }
                                />
                            </div>
                        </div>
                    </section>
                </div>

                <div className="ds-prev">
                    <div className="ds-prevbar">
                        <div className="t">Live preview · A4</div>
                        {isQuote && data.valid_until && (
                            <div
                                className="t"
                                style={{ color: 'var(--color-ds-red)' }}
                            >
                                VALID UNTIL {data.valid_until}
                            </div>
                        )}
                    </div>

                    <DocumentPaper
                        title={TYPE_TITLES[data.doc_type] ?? 'DOCUMENT'}
                        typeLabel={TYPE_LABELS[data.doc_type] ?? 'Document'}
                        number={existing?.number ?? placeholderNumber}
                        business={business}
                        billTo={{
                            company: data.company,
                            contact_name: data.contact_name,
                            phone: data.phone,
                            vat_number: data.vat_number,
                            delivery_address: data.delivery_address,
                        }}
                        issuedOn={data.issued_on}
                        validUntil={isQuote ? data.valid_until : null}
                        dueOn={isQuote ? null : data.due_on}
                        fulfilment={data.fulfilment}
                        lines={data.lines}
                        totals={totals}
                        vatMode={vatRegistered ? data.vat_mode : 'none'}
                        vatRate={vatRate}
                        notes={data.notes}
                        terms={
                            existing?.terms ??
                            terms[data.doc_type] ??
                            terms.quote
                        }
                        banking={existing?.banking ?? banking}
                    />

                    <div className="ds-prevact">
                        <button
                            type="button"
                            className="ds-btn ds-btn-red"
                            onClick={save}
                            disabled={form.processing}
                        >
                            {form.processing
                                ? 'Saving…'
                                : existing
                                  ? 'Save'
                                  : 'Save draft'}
                        </button>

                        {existing && (
                            <>
                                <a
                                    className="ds-btn ds-btn-dark"
                                    href={`/admin/documents/${existing.id}/pdf`}
                                >
                                    Download PDF
                                </a>
                                <a
                                    className="ds-btn ds-btn-ghost"
                                    href={`/admin/documents/${existing.id}/print`}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    Print
                                </a>
                            </>
                        )}
                    </div>

                    {existing && (
                        <div
                            style={{
                                display: 'flex',
                                gap: 8,
                                padding: '0 14px 14px',
                                background: 'var(--color-ds-mist)',
                                flexWrap: 'wrap',
                            }}
                        >
                            {existing.status === 'draft' && (
                                <button
                                    type="button"
                                    className="ds-btn ds-btn-dark"
                                    style={{ flex: 1 }}
                                    onClick={() =>
                                        router.post(
                                            `/admin/documents/${existing.id}/issue`,
                                        )
                                    }
                                >
                                    Issue &amp; email
                                </button>
                            )}

                            {existing.doc_type === 'quote' &&
                                !existing.is_expired && (
                                    <button
                                        type="button"
                                        className="ds-btn ds-btn-ghost"
                                        style={{ flex: 1 }}
                                        onClick={() =>
                                            router.post(
                                                `/admin/documents/${existing.id}/convert`,
                                            )
                                        }
                                    >
                                        Convert to invoice
                                    </button>
                                )}

                            {existing.doc_type === 'invoice' &&
                                existing.status !== 'paid' && (
                                    <button
                                        type="button"
                                        className="ds-btn ds-btn-ghost"
                                        style={{ flex: 1 }}
                                        onClick={() =>
                                            router.post(
                                                `/admin/documents/${existing.id}/status`,
                                                {
                                                    status: 'paid',
                                                },
                                            )
                                        }
                                    >
                                        Mark paid
                                    </button>
                                )}

                            {existing.public_url && (
                                <a
                                    className="ds-btn ds-btn-ghost"
                                    style={{ flex: 1 }}
                                    href={existing.public_url}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    Customer link
                                </a>
                            )}
                        </div>
                    )}
                </div>
            </div>
        </DsAdminLayout>
    );
}

function Err({ message }: { message?: string }) {
    return message ? <div className="err">{message}</div> : null;
}

function defaultLine(grades: Grade[]): LineForm {
    const grade = grades[0];

    return {
        grade_id: grade?.id ?? null,
        code: grade?.code ?? null,
        description: grade?.label ?? 'Diesel',
        litres: '',
        price_per_litre: decimalInput(grade?.price_per_litre, 2),
    };
}

/**
 * Stored values carry the column's full scale. Nobody wants to edit
 * '12000.00' or '23.7000', so they are trimmed on the way into the form.
 */
function tidyLine(line: LineForm): LineForm {
    return {
        ...line,
        litres: decimalInput(line.litres),
        price_per_litre: decimalInput(line.price_per_litre, 2),
    };
}

function addHours(date: string, hours: number): string {
    const value = new Date(`${date}T00:00:00`);
    value.setHours(value.getHours() + hours);

    return value.toISOString().slice(0, 10);
}

function addDays(date: string, days: number): string {
    const value = new Date(`${date}T00:00:00`);
    value.setDate(value.getDate() + days);

    return value.toISOString().slice(0, 10);
}
