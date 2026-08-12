@extends('layouts.site')

@section('title', 'Bulk diesel supply — 50ppm and 500ppm | Dynamic Solutions')
@section('description', 'Bulk diesel by the tanker load: 50ppm low sulphur and 500ppm standard, delivered free around Gauteng or collected at the pump in Benoni. SARS diesel rebate documentation supported.')

@section('content')
    <div class="ds-hero" style="padding-bottom:44px">
        <div class="inner" style="grid-template-columns:1.15fr .85fr">
            <div>
                <div class="ds-eyebrow">Bulk supply</div>
                <h1 style="font-size:48px">BULK<br><span>DIESEL</span></h1>
                <p class="lede">
                    Tanker loads of 50ppm and 500ppm, delivered to site or collected at the pump.
                    Standing fleet orders, site tank fills and generator top-ups.
                </p>
                <div class="cta" style="padding-bottom:0">
                    <a class="ds-btn ds-btn-red" href="{{ route('quote') }}">Get a quote</a>
                    <a class="ds-btn ds-btn-out"
                       href="{{ whatsapp_link('Hi Dynamic Solutions, I would like a bulk diesel quote.') }}"
                       rel="noopener" target="_blank">WhatsApp us</a>
                </div>
            </div>

            @include('partials.pump-panel')
        </div>
    </div>

    <div class="ds-sec">
        <div class="ds-eyebrow">Grades</div>
        <h2>WHAT WE SUPPLY</h2>

        <div class="ds-svcs" style="grid-template-columns:repeat(2,1fr)">
            @foreach ($prices as $row)
                <div class="ds-svc">
                    <h3>{{ $row['label'] }}</h3>
                    <div class="ds-num" style="font-size:30px;font-weight:700;color:var(--color-ds-red);letter-spacing:-.02em">
                        R{{ number_format((float) $row['price_per_litre'], 2) }}<span
                            style="font-size:13px;color:var(--color-ds-mute);letter-spacing:.1em;margin-left:6px">/ L</span>
                    </div>
                    <div class="ds-num" style="font-size:9px;color:var(--color-ds-mute);letter-spacing:.05em;margin:6px 0 14px">
                        CODE {{ $row['code'] }} ·
                        UPDATED {{ Str::upper(\Illuminate\Support\Carbon::parse($row['effective_from'])->timezone(config('app.timezone'))->format('j M Y')) }} ·
                        {{ $row['vat_inclusive'] ? 'INCL. VAT' : 'EXCL. VAT' }} · SUBJECT TO CHANGE
                    </div>
                    <p>
                        @if ($row['id'] === '50ppm')
                            Low sulphur diesel for modern common-rail engines and equipment under
                            warranty. The safe default for a mixed fleet.
                        @else
                            Standard diesel for older engines, plant, generators and agricultural
                            use where a 50ppm specification is not required.
                        @endif
                    </p>
                </div>
            @endforeach

            @if (empty($prices))
                <div class="ds-svc">
                    <h3>Price on request</h3>
                    <p>
                        Today's rate has not been published yet. Call
                        {{ settings('business.phone') }} and we will quote you on the spot.
                    </p>
                </div>
            @endif
        </div>
    </div>

    <div class="ds-split">
        <div class="l">
            <div class="ds-eyebrow">How it works</div>
            <h2 style="font-family:var(--font-display);font-size:28px;margin:8px 0 0">ORDERING</h2>
            <ul style="list-style:none;padding:0;margin:20px 0 0;font-size:13px;line-height:1.62;color:var(--color-ds-steel)">
                <li style="padding:11px 0;border-bottom:1px solid var(--color-ds-line)">
                    <b style="color:var(--color-ds-ink)">Minimum order</b><br>
                    <span class="ds-num">{{ number_format($minOrderLitres) }} litres</span> for delivery.
                    Smaller volumes are welcome at the pump.
                </li>
                <li style="padding:11px 0;border-bottom:1px solid var(--color-ds-line)">
                    <b style="color:var(--color-ds-ink)">Tanker capacity</b><br>
                    Interlink and rigid loads. Split deliveries across multiple sites on request.
                </li>
                <li style="padding:11px 0;border-bottom:1px solid var(--color-ds-line)">
                    <b style="color:var(--color-ds-ink)">Payment</b><br>
                    COD accepted. EFT before dispatch, or agreed account terms.
                </li>
                <li style="padding:11px 0">
                    <b style="color:var(--color-ds-ink)">Lead time</b><br>
                    Same-day or next-day around Gauteng, subject to load scheduling.
                </li>
            </ul>
        </div>

        <div class="r">
            <div class="ds-eyebrow">SARS diesel rebate</div>
            <p style="font-size:13px;line-height:1.62;color:#C4CAD0;margin:14px 0 0">
                Qualifying users in farming, forestry, fishing and mining can claim a portion of
                the fuel levy back from SARS under the diesel refund scheme. Claims need invoices
                showing the litres, the date, the delivery address and our details.
            </p>
            <p style="font-size:13px;line-height:1.62;color:#C4CAD0;margin:14px 0 0">
                Every invoice we issue carries all of it, so your logbook and your claim reconcile
                without you having to chase us for paperwork.
            </p>
            <p style="font-size:11px;line-height:1.62;color:#8A9096;margin:18px 0 0">
                Eligibility and rebate rates are set by SARS and change. We supply the
                documentation; we cannot advise on whether you qualify — speak to your accountant.
            </p>
        </div>
    </div>
@endsection
