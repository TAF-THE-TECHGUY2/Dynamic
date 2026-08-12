import { litres as formatLitres, money, rate } from '@/lib/money';
import type { TotalsResult, VatMode } from '@/lib/totals';

export type PaperLine = {
    code?: string | null;
    description: string;
    litres?: string | null;
    price_per_litre?: string | null;
};

export type BusinessBlock = {
    trading_name: string;
    registered_name: string;
    reg_number: string;
    vat_number: string;
    phone: string;
    address_line1: string;
    address_line2: string;
    address_line3: string;
};

/**
 * The live A4 preview.
 *
 * Deliberately the same ds-paper classes as resources/views/documents/paper.blade.php,
 * so the preview and the printed PDF are the same document. When you change one,
 * change the other — the CSS is shared but the markup is not.
 */
export default function DocumentPaper({
    title,
    typeLabel,
    number,
    business,
    billTo,
    issuedOn,
    validUntil,
    dueOn,
    fulfilment,
    lines,
    totals,
    vatMode,
    vatRate,
    notes,
    terms,
    banking,
}: {
    title: string;
    typeLabel: string;
    number: string;
    business: BusinessBlock;
    billTo: {
        company: string;
        contact_name: string;
        phone: string;
        vat_number: string;
        delivery_address: string;
    };
    issuedOn: string;
    validUntil: string | null;
    dueOn: string | null;
    fulfilment: string;
    lines: PaperLine[];
    totals: TotalsResult;
    vatMode: VatMode;
    vatRate: string;
    notes: string;
    terms: string;
    banking: string;
}) {
    const showsVat = vatMode !== 'none';
    const vatPercent = trimZeros((Number(vatRate) * 100).toFixed(2));

    return (
        <div className="ds-paper">
            <div className="ptop">
                <div>
                    <div className="ds-logo">
                        <div className="ds-mark">
                            <span className="d">D</span>
                            <span>S</span>
                        </div>
                        <div className="ds-slash" />
                        <div>
                            <div className="w1">DYNAMIC</div>
                            <div className="w2">SOLUTIONS</div>
                        </div>
                    </div>
                    <div className="tagline">
                        Fueling your business. Powering your journey.
                    </div>
                </div>

                <div style={{ minWidth: 150 }}>
                    <div className="dt">{title}</div>
                    {/* motif 3 of 3 — the slash beneath the document title */}
                    <div className="dtr" />
                    <div className="pfrom">
                        <strong>
                            {business.registered_name || business.trading_name}
                        </strong>
                        <br />
                        {business.address_line1}
                        <br />
                        {business.address_line2}
                        <br />
                        Mobile: {business.phone}
                        {business.reg_number && (
                            <>
                                <br />
                                Reg: {business.reg_number}
                            </>
                        )}
                        {business.vat_number && (
                            <>
                                <br />
                                VAT: {business.vat_number}
                            </>
                        )}
                    </div>
                </div>
            </div>

            <hr />

            <div className="pmeta">
                <div>
                    <div className="plbl">Bill to</div>
                    <div className="pname">{billTo.company || '—'}</div>
                    <div className="paddr">
                        {billTo.contact_name && (
                            <>
                                {billTo.contact_name}
                                <br />
                            </>
                        )}
                        {billTo.delivery_address
                            .split('\n')
                            .filter(Boolean)
                            .map((line, index) => (
                                <span key={index}>
                                    {line}
                                    <br />
                                </span>
                            ))}
                        {billTo.phone && (
                            <>
                                {billTo.phone}
                                <br />
                            </>
                        )}
                        {billTo.vat_number && <>VAT: {billTo.vat_number}</>}
                    </div>
                </div>

                <div>
                    <div className="mr">
                        <div className="mk">{typeLabel} number:</div>
                        <div className="mv">{number}</div>
                    </div>
                    <div className="mr">
                        <div className="mk">Date issued:</div>
                        <div className="mv">{formatDate(issuedOn)}</div>
                    </div>
                    {validUntil && (
                        <div className="mr vv">
                            <div className="mk">Valid until:</div>
                            <div className="mv">{formatDate(validUntil)}</div>
                        </div>
                    )}
                    {dueOn && (
                        <div className="mr vv">
                            <div className="mk">Payment due:</div>
                            <div className="mv">{formatDate(dueOn)}</div>
                        </div>
                    )}
                    <div className="mr">
                        <div className="mk">Fulfilment:</div>
                        <div className="mv">
                            {fulfilment === 'delivery'
                                ? 'Delivery'
                                : 'Collection'}
                        </div>
                    </div>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Items</th>
                        <th>Quantity</th>
                        <th>Price / L</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    {lines.map((line, index) => (
                        <tr key={index}>
                            <td>
                                {line.code && (
                                    <>
                                        <b>{line.code}</b>
                                        <br />
                                    </>
                                )}
                                <span
                                    style={{ color: 'var(--color-ds-steel)' }}
                                >
                                    {line.description || 'Diesel'}
                                </span>
                            </td>
                            <td>
                                {line.litres
                                    ? `${formatLitres(line.litres)} L`
                                    : '1'}
                            </td>
                            <td>
                                {line.price_per_litre
                                    ? rate(line.price_per_litre)
                                    : '—'}
                            </td>
                            <td>
                                {money(totals.lineAmountsCents[index] ?? 0)}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>

            <div className="pt">
                <div className="ptb">
                    {showsVat && (
                        <>
                            <div className="ptr">
                                <div className="k">Subtotal</div>
                                <div className="v">
                                    {money(totals.subtotalCents)}
                                </div>
                            </div>
                            <div className="ptr">
                                <div className="k">VAT @ {vatPercent}%</div>
                                <div className="v">
                                    {money(totals.vatCents)}
                                </div>
                            </div>
                        </>
                    )}
                    <div className="grand">
                        <div className="k">TOTAL (ZAR)</div>
                        <div className="v">{money(totals.totalCents)}</div>
                    </div>
                </div>
            </div>

            {notes && (
                <div style={{ marginTop: 18 }}>
                    <h5
                        style={{
                            fontSize: 8,
                            letterSpacing: '.16em',
                            textTransform: 'uppercase',
                            color: 'var(--color-ds-mute)',
                            fontWeight: 600,
                            margin: '0 0 5px',
                        }}
                    >
                        Notes
                    </h5>
                    <div
                        style={{
                            fontSize: 9,
                            lineHeight: 1.62,
                            color: 'var(--color-ds-steel)',
                            whiteSpace: 'pre-line',
                        }}
                    >
                        {notes}
                    </div>
                </div>
            )}

            <div className="pcols">
                <div>
                    <h5>Banking details</h5>
                    <div className="body mono">{banking}</div>
                </div>
                <div>
                    <h5>Terms</h5>
                    <div className="body">{terms}</div>
                </div>
            </div>

            <div className="pfoot">
                TRUSTED FUEL. <em>DRIVEN BY SERVICE.</em>
            </div>
        </div>
    );
}

function formatDate(value: string): string {
    if (!value) {
        return '—';
    }

    const date = new Date(`${value}T00:00:00`);

    return Number.isNaN(date.getTime())
        ? value
        : date.toLocaleDateString('en-ZA', {
              day: 'numeric',
              month: 'short',
              year: 'numeric',
          });
}

function trimZeros(value: string): string {
    return value.replace(/\.?0+$/, '');
}
