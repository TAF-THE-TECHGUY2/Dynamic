@php($phone = settings('business.phone', '061 687 4334'))
@php($email = settings('business.email', ''))
@php($registered = settings('business.registered_name', ''))
@php($regNumber = settings('business.reg_number', ''))
@php($vatNumber = settings('business.vat_number', ''))

<div class="ds-footcta">
    <div class="big">ORDER NOW. <em>QUALITY FUEL, ON TIME.</em></div>
    <a class="ds-btn ds-btn-red" href="{{ route('quote') }}">Request a quote</a>
</div>

<footer class="ds-footer">
    <div class="cols">
        <div>
            <div class="ds-logo">
                <div class="ds-mark" style="font-size:24px"><span class="d">D</span><span>S</span></div>
                <div class="ds-slash" style="width:4px;height:20px"></div>
                <div>
                    <div class="w1" style="font-size:14px;color:#fff">DYNAMIC</div>
                    <div class="w2" style="font-size:6.5px">SOLUTIONS</div>
                </div>
            </div>
            <p style="margin:14px 0 0;max-width:280px">
                Fueling your business. Powering your journey.
            </p>
            <div class="ds-num" style="margin-top:12px;font-size:11px;letter-spacing:.04em">
                {{ settings('business.address_line1') }}<br>
                {{ settings('business.address_line2') }}<br>
                {{ settings('business.address_line3') }}
            </div>
        </div>

        <div>
            <h2>Company</h2>
            <p style="margin:0">
                <a href="{{ route('bulk-diesel') }}">Bulk diesel</a><br>
                <a href="{{ route('truck-stop') }}">Truck stop &amp; yard</a><br>
                <a href="{{ route('quote') }}">Get a quote</a><br>
                <a href="{{ route('contact') }}">Contact</a>
            </p>
        </div>

        <div>
            <h2>Talk to us</h2>
            <p style="margin:0">
                <a class="ds-num" href="tel:{{ preg_replace('/\s+/', '', $phone) }}">{{ $phone }}</a><br>
                <a href="{{ whatsapp_link('Hi Dynamic Solutions, I would like a diesel quote.') }}"
                   rel="noopener" target="_blank">WhatsApp us</a>
                @if ($email !== '' && ! str_starts_with($email, 'TODO'))
                    <br><a href="mailto:{{ $email }}">{{ $email }}</a>
                @endif
            </p>
            <p style="margin:12px 0 0">
                {{ settings('business.hours_weekday') }}<br>
                {{ settings('business.hours_saturday') }}<br>
                {{ settings('business.hours_note') }}
            </p>
        </div>
    </div>

    <div class="legal">
        &copy; {{ now()->year }}
        {{ str_starts_with($registered, 'TODO') ? 'Dynamic Solutions' : $registered }}
        @unless (str_starts_with($regNumber, 'TODO') || $regNumber === '')
            · REG {{ $regNumber }}
        @endunless
        @unless (str_starts_with($vatNumber, 'TODO') || $vatNumber === '')
            · VAT {{ $vatNumber }}
        @endunless
        · ALL PRICES SUBJECT TO CHANGE
    </div>
</footer>
