@extends('layouts.site')

@section('title', 'Contact — 31 Dassie Road, Zesfontein, Benoni | Dynamic Solutions')
@section('description', 'Call 061 687 4334, WhatsApp us or drive in to 31 Dassie Road, Zesfontein, Benoni, Gauteng. Bulk diesel and secure truck parking.')

@section('content')
    <div class="ds-sec">
        <div class="ds-eyebrow">Talk to us</div>
        <h2>GET IN TOUCH</h2>
        <p class="intro">
            Fastest answer is a phone call or a WhatsApp — we are usually in the yard. Leave a
            message below and we will come back to you.
        </p>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:30px" class="ds-contact-grid">
            <div class="ds-pane">
                <div class="ph"><h3>Send a message</h3></div>
                <div class="pb" style="padding-top:4px">
                    @if (session('contact.sent'))
                        <div class="ds-banner ok" style="margin-bottom:8px">
                            <b>Message sent</b>
                            Thanks — we have it, and we will come back to you shortly.
                        </div>
                    @endif

                    <form method="POST" action="{{ route('contact.send') }}">
                        @csrf

                        <div class="ds-g2">
                            <div class="ds-fld">
                                <label for="contact-name">Your name</label>
                                <input id="contact-name" name="name" required maxlength="120"
                                       value="{{ old('name') }}">
                                @error('name')<div class="err">{{ $message }}</div>@enderror
                            </div>
                            <div class="ds-fld">
                                <label for="contact-company">Company</label>
                                <input id="contact-company" name="company" maxlength="160"
                                       value="{{ old('company') }}">
                                @error('company')<div class="err">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="ds-g2">
                            <div class="ds-fld">
                                <label for="contact-phone">Mobile</label>
                                <input id="contact-phone" name="phone" class="mono" required
                                       inputmode="tel" maxlength="40" value="{{ old('phone') }}">
                                @error('phone')<div class="err">{{ $message }}</div>@enderror
                            </div>
                            <div class="ds-fld">
                                <label for="contact-email">Email</label>
                                <input id="contact-email" name="email" type="email" maxlength="160"
                                       value="{{ old('email') }}">
                                @error('email')<div class="err">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="ds-fld">
                            <label for="contact-message">What do you need?</label>
                            <textarea id="contact-message" name="message" rows="4" required
                                      maxlength="2000">{{ old('message') }}</textarea>
                            @error('message')<div class="err">{{ $message }}</div>@enderror
                        </div>

                        <button type="submit" class="ds-btn ds-btn-red" style="width:100%;margin-top:14px">
                            Send message
                        </button>
                    </form>
                </div>
            </div>

            <div>
                <div class="ds-pane">
                    <div class="ph"><h3>The yard</h3></div>
                    <div class="pb">
                        <div class="ds-addr" style="margin-top:0">
                            {{ settings('business.address_line1') }}<br>
                            {{ settings('business.address_line2') }}<br>
                            {{ settings('business.address_line3') }}
                        </div>

                        <div style="margin-top:16px;display:grid;gap:10px">
                            <a class="ds-btn ds-btn-red"
                               href="tel:{{ preg_replace('/\s+/', '', settings('business.phone', '')) }}">
                                Call {{ settings('business.phone') }}
                            </a>
                            <a class="ds-btn ds-btn-dark" rel="noopener" target="_blank"
                               href="{{ whatsapp_link('Hi Dynamic Solutions, I have a question.') }}">
                                WhatsApp {{ settings('business.phone') }}
                            </a>
                            <a class="ds-btn ds-btn-ghost" rel="noopener" target="_blank"
                               href="https://www.google.com/maps/search/?api=1&query={{ urlencode(implode(', ', array_filter([
                                   settings('business.address_line1'),
                                   settings('business.address_line2'),
                                   settings('business.address_line3'),
                               ]))) }}">
                                Open in Google Maps
                            </a>
                        </div>
                    </div>
                </div>

                <div class="ds-pane" style="margin-top:16px">
                    <div class="ph"><h3>Hours</h3></div>
                    <div class="pb">
                        <div style="font-size:13px;line-height:1.9;color:var(--color-ds-steel)">
                            {{ settings('business.hours_weekday') }}<br>
                            {{ settings('business.hours_saturday') }}<br>
                            {{ settings('business.hours_note') }}
                        </div>
                    </div>
                </div>

                @php($email = settings('business.email', ''))
                @if ($email !== '' && ! str_starts_with($email, 'TODO'))
                    <div class="ds-pane" style="margin-top:16px">
                        <div class="ph"><h3>Email</h3></div>
                        <div class="pb">
                            <a class="ds-num" style="font-size:13px;color:var(--color-ds-red);text-decoration:none"
                               href="mailto:{{ $email }}">{{ $email }}</a>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

@push('head')
    <style>
        @media (max-width: 980px) {
            .ds-contact-grid { grid-template-columns: 1fr !important; }
        }
    </style>
@endpush
