import { router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import DsAdminLayout from '@/layouts/ds-admin-layout';

type Booking = {
    id: string;
    company: string;
    vehicle_reg: string;
    bay: string | null;
    check_in_display: string;
    check_out_display: string | null;
    days: number;
    charge_display: string;
    is_open: boolean;
};

type Props = {
    bays: number;
    occupied: number;
    free: number;
    defaultRate: string | null;
    open: Booking[];
    recent: Booking[];
    customers: { id: string; company: string }[];
};

export default function YardIndex({
    bays,
    occupied,
    free,
    defaultRate,
    open,
    recent,
    customers,
}: Props) {
    const form = useForm({
        customer_id: '',
        company: '',
        vehicle_reg: '',
        bay: '',
        rate_per_day: defaultRate ?? '',
        notes: '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post('/admin/yard', {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    }

    const occupancy =
        bays > 0 ? Math.min(100, Math.round((occupied / bays) * 100)) : 0;

    return (
        <DsAdminLayout
            title="Yard bookings"
            breadcrumb="Operations / Yard"
            heading="YARD BOOKINGS"
        >
            <div className="ds-cards ds-cards-3">
                <div className="ds-card">
                    <div className="k">Bays taken</div>
                    <div className="v">
                        {occupied}
                        <span
                            style={{
                                fontSize: 14,
                                color: 'var(--color-ds-mute)',
                            }}
                        >
                            {' '}
                            / {bays}
                        </span>
                    </div>
                    <div
                        style={{
                            height: 8,
                            background: 'var(--color-ds-mist)',
                            borderRadius: 2,
                            marginTop: 10,
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
                </div>
                <div className="ds-card">
                    <div className="k">Bays free</div>
                    <div className="v">{free}</div>
                    <div className="d flat">{occupancy}% occupied</div>
                </div>
                <div className="ds-card">
                    <div className="k">Day rate</div>
                    <div className="v">
                        {defaultRate
                            ? `R${Number(defaultRate).toFixed(2)}`
                            : '—'}
                    </div>
                    <div className="d flat">
                        {defaultRate
                            ? 'Per vehicle, part days count as whole'
                            : 'Set one in Settings'}
                    </div>
                </div>
            </div>

            <div className="ds-two">
                <div>
                    <div className="ds-pane">
                        <div className="ph">
                            <h3>In the yard now</h3>
                        </div>
                        <table className="ds-tbl">
                            <thead>
                                <tr>
                                    <th>Vehicle</th>
                                    <th>Bay</th>
                                    <th>Checked in</th>
                                    <th>Days</th>
                                    <th>Charge</th>
                                    <th />
                                </tr>
                            </thead>
                            <tbody>
                                {open.length === 0 ? (
                                    <tr className="empty">
                                        <td colSpan={6}>The yard is empty.</td>
                                    </tr>
                                ) : (
                                    open.map((booking) => (
                                        <tr key={booking.id}>
                                            <td>
                                                <div className="ds-cust">
                                                    {booking.vehicle_reg}
                                                </div>
                                                <div className="ds-qref">
                                                    {booking.company}
                                                </div>
                                            </td>
                                            <td>{booking.bay ?? '—'}</td>
                                            <td>{booking.check_in_display}</td>
                                            <td>{booking.days}</td>
                                            <td>{booking.charge_display}</td>
                                            <td>
                                                <button
                                                    type="button"
                                                    className="ds-linkred"
                                                    onClick={() =>
                                                        router.post(
                                                            `/admin/yard/${booking.id}/check-out`,
                                                            {},
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                >
                                                    Check out
                                                </button>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    <div className="ds-pane" style={{ marginTop: 16 }}>
                        <div className="ph">
                            <h3>Recently checked out</h3>
                        </div>
                        <table className="ds-tbl">
                            <thead>
                                <tr>
                                    <th>Vehicle</th>
                                    <th>Out</th>
                                    <th>Days</th>
                                    <th>Charge</th>
                                </tr>
                            </thead>
                            <tbody>
                                {recent.length === 0 ? (
                                    <tr className="empty">
                                        <td colSpan={4}>Nothing yet.</td>
                                    </tr>
                                ) : (
                                    recent.map((booking) => (
                                        <tr key={booking.id}>
                                            <td>
                                                <div className="ds-cust">
                                                    {booking.vehicle_reg}
                                                </div>
                                                <div className="ds-qref">
                                                    {booking.company}
                                                </div>
                                            </td>
                                            <td>{booking.check_out_display}</td>
                                            <td>{booking.days}</td>
                                            <td>{booking.charge_display}</td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                <form className="ds-pane" onSubmit={submit}>
                    <div className="ph">
                        <h3>Check a truck in</h3>
                    </div>
                    <div className="pb" style={{ paddingTop: 4 }}>
                        <div className="ds-fld">
                            <label htmlFor="y-reg">Vehicle registration</label>
                            <input
                                id="y-reg"
                                className="mono"
                                value={form.data.vehicle_reg}
                                onChange={(event) =>
                                    form.setData(
                                        'vehicle_reg',
                                        event.target.value.toUpperCase(),
                                    )
                                }
                                required
                            />
                            {form.errors.vehicle_reg && (
                                <div className="err">
                                    {form.errors.vehicle_reg}
                                </div>
                            )}
                        </div>

                        <div className="ds-fld">
                            <label htmlFor="y-customer">Customer</label>
                            <select
                                id="y-customer"
                                value={form.data.customer_id}
                                onChange={(event) =>
                                    form.setData(
                                        'customer_id',
                                        event.target.value,
                                    )
                                }
                            >
                                <option value="">Walk-in</option>
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

                        {form.data.customer_id === '' && (
                            <div className="ds-fld">
                                <label htmlFor="y-company">
                                    Company (walk-in)
                                </label>
                                <input
                                    id="y-company"
                                    value={form.data.company}
                                    onChange={(event) =>
                                        form.setData(
                                            'company',
                                            event.target.value,
                                        )
                                    }
                                />
                            </div>
                        )}

                        <div className="ds-g2">
                            <div className="ds-fld">
                                <label htmlFor="y-bay">Bay</label>
                                <input
                                    id="y-bay"
                                    className="mono"
                                    value={form.data.bay}
                                    onChange={(event) =>
                                        form.setData('bay', event.target.value)
                                    }
                                />
                            </div>
                            <div className="ds-fld">
                                <label htmlFor="y-rate">Rate / day</label>
                                <input
                                    id="y-rate"
                                    className="mono"
                                    inputMode="decimal"
                                    value={form.data.rate_per_day}
                                    onChange={(event) =>
                                        form.setData(
                                            'rate_per_day',
                                            event.target.value,
                                        )
                                    }
                                />
                                {form.errors.rate_per_day && (
                                    <div className="err">
                                        {form.errors.rate_per_day}
                                    </div>
                                )}
                            </div>
                        </div>

                        <div className="ds-fld">
                            <label htmlFor="y-notes">Notes</label>
                            <textarea
                                id="y-notes"
                                rows={2}
                                value={form.data.notes}
                                onChange={(event) =>
                                    form.setData('notes', event.target.value)
                                }
                            />
                        </div>

                        <button
                            type="submit"
                            className="ds-btn ds-btn-red"
                            style={{ width: '100%', marginTop: 14 }}
                            disabled={form.processing || free === 0}
                        >
                            {free === 0 ? 'Yard is full' : 'Check in'}
                        </button>
                    </div>
                </form>
            </div>
        </DsAdminLayout>
    );
}
