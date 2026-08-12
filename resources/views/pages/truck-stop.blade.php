@extends('layouts.site')

@section('title', 'Truck stop & secure parking, 31 Dassie Road Benoni | Dynamic Solutions')
@section('description', 'Secure overnight and long-stay truck parking in Zesfontein, Benoni. Pump on site, weighbridge-friendly access for interlink and rigid trucks.')

@section('content')
    <div class="ds-hero" style="padding-bottom:44px">
        <div class="inner" style="grid-template-columns:1.15fr .85fr">
            <div>
                <div class="ds-eyebrow">Truck stop · Zesfontein</div>
                <h1 style="font-size:48px">SECURE<br><span>PARKING</span></h1>
                <p class="lede">
                    Park up for the night or leave a trailer for the week. A pump on site means you
                    refuel before you roll out instead of losing an hour to a detour.
                </p>
                <div class="cta" style="padding-bottom:0">
                    <a class="ds-btn ds-btn-red"
                       href="{{ whatsapp_link('Hi Dynamic Solutions, I would like to book a parking bay.') }}"
                       rel="noopener" target="_blank">Book a bay</a>
                    <a class="ds-btn ds-btn-out"
                       href="tel:{{ preg_replace('/\s+/', '', settings('business.phone', '')) }}">
                        Call {{ settings('business.phone') }}
                    </a>
                </div>
            </div>

            <div class="ds-pump">
                <div class="rule"></div>
                <div class="grade">
                    <div class="gname">YARD CAPACITY</div>
                    <div class="gsub">31 DASSIE ROAD</div>
                </div>
                <div class="digits" style="color:#fff">{{ $bays }}<small>BAYS</small></div>

                <div class="second">
                    <div class="grade">
                        <div class="gname">DAY RATE</div>
                        <div class="gsub">PER VEHICLE</div>
                    </div>
                    <div class="digits">
                        @if ($ratePerDay)
                            R{{ number_format((float) $ratePerDay, 2) }}<small>/ DAY</small>
                        @else
                            <span style="font-size:20px">ON REQUEST</span>
                        @endif
                    </div>
                </div>

                <div class="stamp">SUBJECT TO AVAILABILITY · SUBJECT TO CHANGE</div>
            </div>
        </div>
    </div>

    <div class="ds-trust">
        <div class="t"><div class="k">GATED YARD</div><div class="v">Controlled access</div></div>
        <div class="t"><div class="k">PUMP ON SITE</div><div class="v">Refuel before you leave</div></div>
        <div class="t"><div class="k">INTERLINK OK</div><div class="v">Room to turn</div></div>
        <div class="t"><div class="k">LONG STAY</div><div class="v">Weekly and monthly</div></div>
    </div>

    <div class="ds-sec">
        <div class="ds-eyebrow">Facilities</div>
        <h2>WHAT YOU GET</h2>

        <div class="ds-svcs">
            <div class="ds-svc">
                <span class="ic">@include('partials.icons.yard')</span>
                <h3>Overnight &amp; long stay</h3>
                <p>
                    Bays for rigids, interlinks and standalone trailers. Night stops, weekly
                    layovers and monthly standing arrangements.
                </p>
            </div>
            <div class="ds-svc">
                <span class="ic">@include('partials.icons.pump')</span>
                <h3>Diesel at the pump</h3>
                <p>
                    Fill up on the way in or the way out at the same bulk rate we quote for
                    delivery. No detour, no queue.
                </p>
            </div>
            <div class="ds-svc">
                <span class="ic">@include('partials.icons.tanker')</span>
                <h3>Access for big rigs</h3>
                <p>
                    Weighbridge-friendly approach and space to manoeuvre a full combination without
                    a shunt.
                </p>
            </div>
        </div>
    </div>

    <div class="ds-split">
        <div class="l">
            <div class="ds-eyebrow">Access hours</div>
            <h2 style="font-family:var(--font-display);font-size:28px;margin:8px 0 0">GATE TIMES</h2>
            <div class="ds-addr">
                {{ settings('business.hours_weekday') }}<br>
                {{ settings('business.hours_saturday') }}<br>
                {{ settings('business.hours_note') }}
            </div>
            <p class="intro">
                Booked vehicles can be let in outside these hours by arrangement — call ahead so
                the gate is expecting you.
            </p>
            <a class="ds-btn ds-btn-dark" style="margin-top:8px" rel="noopener" target="_blank"
               href="https://www.google.com/maps/search/?api=1&query={{ urlencode(implode(', ', array_filter([
                   settings('business.address_line1'),
                   settings('business.address_line2'),
                   settings('business.address_line3'),
               ]))) }}">
                Open in Google Maps
            </a>
        </div>

        <div class="r">
            <div class="ds-eyebrow">Booking a bay</div>
            <ul class="list">
                <li><b>Call or WhatsApp</b> Tell us the vehicle, the registration and the dates</li>
                <li><b>Arrive</b> We log the vehicle in against your company at the gate</li>
                <li><b>Stay</b> Charged per day, part days counted as whole days</li>
                <li><b>Leave</b> We check you out and invoice the stay</li>
            </ul>
        </div>
    </div>
@endsection
