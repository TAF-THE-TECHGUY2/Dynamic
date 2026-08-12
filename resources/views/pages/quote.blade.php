@extends('layouts.site')

@section('title', 'Get a diesel estimate | Dynamic Solutions')
@section('description', 'Work out what a bulk diesel load costs at today\'s rate, then send it to us on WhatsApp or by email. Free delivery around Gauteng.')

@section('content')
    <div class="ds-sec">
        <div class="ds-eyebrow">Estimate</div>
        <h2>WHAT WILL A LOAD COST?</h2>
        <p class="intro">
            Today's rate, worked out on the spot. This is an estimate — we will confirm it as a
            formal quote before anything is delivered.
        </p>

        @if (session('quote.submitted'))
            <div class="ds-banner ok" style="margin-top:22px">
                <b>Estimate sent</b>
                It is on its way to your inbox and we have a copy. We will be in touch to confirm
                a formal quote.
                @if (session('quote.submitted'))
                    <br><a href="{{ session('quote.submitted') }}" style="color:inherit;font-weight:700">
                        View your estimate →
                    </a>
                @endif
            </div>
        @endif

        <form method="POST" action="{{ route('quote.submit') }}"
              x-data="quoteCalculator({
                  endpoint: '{{ route('quote.calculate') }}',
                  csrf: '{{ csrf_token() }}',
                  whatsapp: '{{ preg_replace('/\D+/', '', settings('business.whatsapp', '')) }}',
                  minimum: {{ $minOrderLitres }},
              })">
            @csrf

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:26px" class="ds-quote-grid">
                <div>
                    <div class="ds-pane">
                        <div class="ph"><h3>The load</h3></div>
                        <div class="pb" style="padding-top:4px">
                            <div class="ds-fld">
                                <label for="q-grade">Grade</label>
                                <select id="q-grade" name="grade_id" x-model="gradeId" x-on:change="recalculate()">
                                    @foreach ($prices as $row)
                                        <option value="{{ $row['id'] }}"
                                            @selected(old('grade_id') === $row['id'])>
                                            {{ $row['label'] }} — R{{ number_format((float) $row['price_per_litre'], 2) }}/L
                                        </option>
                                    @endforeach

                                    @if (empty($prices))
                                        <option value="">No price published — please call us</option>
                                    @endif
                                </select>
                                @error('grade_id')<div class="err">{{ $message }}</div>@enderror
                            </div>

                            <div class="ds-g2">
                                <div class="ds-fld">
                                    <label for="q-litres">Litres</label>
                                    <input id="q-litres" name="litres" class="mono" inputmode="decimal"
                                           x-model="litres" x-on:input.debounce.300ms="recalculate()"
                                           value="{{ old('litres', 5000) }}" required>
                                    <div class="hint">
                                        Minimum <span class="ds-num">{{ number_format($minOrderLitres) }}</span> litres for delivery.
                                    </div>
                                    @error('litres')<div class="err">{{ $message }}</div>@enderror
                                </div>

                                <div class="ds-fld">
                                    <label for="q-fulfilment">Delivery or collection</label>
                                    <select id="q-fulfilment" name="fulfilment" x-model="fulfilment">
                                        <option value="delivery" @selected(old('fulfilment', 'delivery') === 'delivery')>
                                            Delivery — free (Gauteng)
                                        </option>
                                        <option value="collection" @selected(old('fulfilment') === 'collection')>
                                            Collection at yard
                                        </option>
                                    </select>
                                    @error('fulfilment')<div class="err">{{ $message }}</div>@enderror
                                </div>
                            </div>

                            <div class="ds-fld" x-show="fulfilment === 'delivery'" x-cloak>
                                <label for="q-suburb">Delivery suburb</label>
                                <input id="q-suburb" name="suburb" maxlength="120"
                                       value="{{ old('suburb') }}" placeholder="Chloorkop, Kempton Park">
                                @error('suburb')<div class="err">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>

                    <div class="ds-pane" style="margin-top:14px">
                        <div class="ph"><h3>Who are we quoting?</h3></div>
                        <div class="pb" style="padding-top:4px">
                            <div class="ds-g2">
                                <div class="ds-fld">
                                    <label for="q-name">Your name</label>
                                    <input id="q-name" name="name" required maxlength="120" value="{{ old('name') }}">
                                    @error('name')<div class="err">{{ $message }}</div>@enderror
                                </div>
                                <div class="ds-fld">
                                    <label for="q-company">Company</label>
                                    <input id="q-company" name="company" maxlength="160" value="{{ old('company') }}">
                                    @error('company')<div class="err">{{ $message }}</div>@enderror
                                </div>
                            </div>

                            <div class="ds-g2">
                                <div class="ds-fld">
                                    <label for="q-phone">Mobile</label>
                                    <input id="q-phone" name="phone" class="mono" inputmode="tel" required
                                           maxlength="40" value="{{ old('phone') }}">
                                    @error('phone')<div class="err">{{ $message }}</div>@enderror
                                </div>
                                <div class="ds-fld">
                                    <label for="q-email">Email</label>
                                    <input id="q-email" name="email" type="email" required maxlength="160"
                                           value="{{ old('email') }}">
                                    @error('email')<div class="err">{{ $message }}</div>@enderror
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="ds-pane">
                    <div class="ph">
                        <h3>Your estimate</h3>
                        <span class="ds-num" style="font-size:9.5px;color:var(--color-ds-mute);letter-spacing:.05em">
                            ESTIMATE ONLY
                        </span>
                    </div>

                    <div class="pb">
                        <div class="ds-num"
                             style="font-size:42px;font-weight:700;letter-spacing:-.02em;color:var(--color-ds-red);line-height:1"
                             x-text="totals.total_display">
                            —
                        </div>
                        <div class="ds-num" style="font-size:9px;color:var(--color-ds-mute);letter-spacing:.05em;margin-top:8px">
                            @if ($lastChangedAt)
                                AT TODAY'S RATE, UPDATED
                                {{ Str::upper($lastChangedAt->timezone(config('app.timezone'))->format('j M Y')) }} ·
                            @endif
                            SUBJECT TO CHANGE
                        </div>

                        <div style="border-top:1px solid var(--color-ds-line);margin-top:16px;padding-top:14px">
                            <div style="display:flex;justify-content:space-between;font-size:12px;padding:4px 0">
                                <span style="color:var(--color-ds-steel);font-weight:600">Litres</span>
                                <span class="ds-num" x-text="totals.litres_display">—</span>
                            </div>
                            <div style="display:flex;justify-content:space-between;font-size:12px;padding:4px 0">
                                <span style="color:var(--color-ds-steel);font-weight:600">Price per litre</span>
                                <span class="ds-num" x-text="totals.price_per_litre_display">—</span>
                            </div>
                            <template x-if="totals.shows_vat">
                                <div>
                                    <div style="display:flex;justify-content:space-between;font-size:12px;padding:4px 0">
                                        <span style="color:var(--color-ds-steel);font-weight:600">Subtotal</span>
                                        <span class="ds-num" x-text="totals.subtotal_display">—</span>
                                    </div>
                                    <div style="display:flex;justify-content:space-between;font-size:12px;padding:4px 0">
                                        <span style="color:var(--color-ds-steel);font-weight:600">VAT @ 15%</span>
                                        <span class="ds-num" x-text="totals.vat_display">—</span>
                                    </div>
                                </div>
                            </template>
                            <div style="display:flex;justify-content:space-between;align-items:center;background:var(--color-ds-ink);color:#fff;padding:10px;margin-top:10px">
                                <span style="font-family:var(--font-display);font-size:9px;letter-spacing:.1em">TOTAL (ZAR)</span>
                                <span class="ds-num" style="font-weight:700;font-size:14px" x-text="totals.total_display">—</span>
                            </div>
                        </div>

                        <template x-if="belowMinimum">
                            <div class="ds-banner warn" style="margin-top:14px">
                                <b>Below the delivery minimum</b>
                                We deliver from <span class="ds-num" x-text="minimum"></span> litres. Smaller
                                volumes are welcome at the pump — call us.
                            </div>
                        </template>

                        <p style="font-size:11px;color:var(--color-ds-mute);line-height:1.62;margin:14px 0 0">
                            This is an estimate at today's published rate, not a quote. Diesel
                            reprices regularly; we will confirm a formal quote with a validity date.
                        </p>
                    </div>

                    <div class="ds-prevact">
                        <a class="ds-btn ds-btn-dark" :href="whatsappHref" target="_blank" rel="noopener">
                            Send via WhatsApp
                        </a>
                        <button type="submit" class="ds-btn ds-btn-red">Email me this</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection

@push('head')
    <style>
        [x-cloak] { display: none !important; }
        @media (max-width: 980px) {
            .ds-quote-grid { grid-template-columns: 1fr !important; }
        }
    </style>
@endpush
