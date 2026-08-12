@extends('layouts.site')

@section('title', 'Diesel available — bulk supply in Benoni, Gauteng | Dynamic Solutions')
@section('description', 'Bulk 50ppm and 500ppm diesel delivered free across Gauteng, plus secure truck parking at 31 Dassie Road, Zesfontein, Benoni. COD accepted, own transport.')

@section('content')
    <div class="ds-hero">
        <div class="diag" aria-hidden="true"></div>

        <div class="inner">
            <div>
                <div class="ds-eyebrow">Bulk supply · Benoni, Gauteng</div>
                <h1>DIESEL<br><span>AVAILABLE</span></h1>
                <p class="lede">
                    Quality fuel, reliable delivery, competitive pricing. Bulk supply to fleets,
                    construction sites and farms across Gauteng — plus a secure yard where your
                    trucks can stop.
                </p>
                <div class="cta">
                    <a class="ds-btn ds-btn-red" href="{{ route('quote') }}">Get a quote</a>
                    <a class="ds-btn ds-btn-out"
                       href="tel:{{ preg_replace('/\s+/', '', settings('business.phone', '')) }}">
                        Call {{ settings('business.phone') }}
                    </a>
                </div>
            </div>

            @include('partials.pump-panel')
        </div>
    </div>

    <div class="ds-trust">
        <div class="t"><div class="k">COD ACCEPTED</div><div class="v">Cash on delivery</div></div>
        <div class="t"><div class="k">TRANSPORT</div><div class="v">Own fleet available</div></div>
        <div class="t"><div class="k">BULK DIESEL</div><div class="v">Tanker loads supplied</div></div>
        <div class="t"><div class="k">FREE DELIVERY</div><div class="v">Around Gauteng</div></div>
    </div>

    <div class="ds-sec">
        <div class="ds-eyebrow">What we do</div>
        <h2>THREE WAYS WE KEEP<br>YOU MOVING</h2>

        <div class="ds-svcs">
            <a class="ds-svc" href="{{ route('bulk-diesel') }}">
                <span class="ic">@include('partials.icons.tanker')</span>
                <h3>Bulk diesel supply</h3>
                <p>
                    50ppm and 500ppm delivered to site by tanker. Tank filling, generator top-ups
                    and standing fleet orders.
                </p>
                <div class="go">View bulk pricing →</div>
            </a>

            <a class="ds-svc" href="{{ route('truck-stop') }}">
                <span class="ic">@include('partials.icons.yard')</span>
                <h3>Truck stop &amp; yard</h3>
                <p>
                    Secure overnight and long-stay parking at 31 Dassie Road. Pump on site, so you
                    refuel before you roll out.
                </p>
                <div class="go">Parking rates →</div>
            </a>

            <a class="ds-svc" href="{{ route('contact') }}">
                <span class="ic">@include('partials.icons.route')</span>
                <h3>Delivery &amp; collection</h3>
                <p>
                    Free delivery around Gauteng, or collect at the pump. Outlying areas quoted on
                    distance.
                </p>
                <div class="go">Check coverage →</div>
            </a>
        </div>
    </div>

    <div class="ds-split">
        <div class="l">
            <div class="ds-eyebrow">Find the yard</div>
            <h2 style="font-family:var(--font-display);font-size:28px;margin:8px 0 0">
                {{ Str::upper(settings('business.address_line1', '31 Dassie Road')) }}
            </h2>
            <div class="ds-addr">
                {{ settings('business.address_line2') }}<br>
                {{ settings('business.address_line3') }}
            </div>
            <p class="intro">
                Drive in for pump fills, bulk collection or to book a parking bay.
                Weighbridge-friendly access for interlink and rigid trucks.
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
            <div class="ds-eyebrow">Who we supply</div>
            <ul class="list">
                <li><b>Logistics</b> Fleet refuelling and standing weekly orders</li>
                <li><b>Construction</b> Site tanks, plant and yellow machinery</li>
                <li><b>Agriculture</b> Farm bulk tanks, rebate-qualifying users</li>
                <li><b>Backup power</b> Generator fills and standby contracts</li>
            </ul>
        </div>
    </div>
@endsection
