{{--
    The document itself.

    Shared verbatim by the browser print route, the Browsershot PDF and the
    public /d/{token} link — one template, no drift.

    Everything below reads from the document's own snapshotted columns. It never
    joins to price_history, customers or settings, because those hold today's
    values and this document holds the values it was issued with. See
    App\Models\Document::forRender().

    @param \App\Models\Document $document
--}}
@php($business = settings()->businessBlock())
@php($showsVat = $document->vat_mode->showsVatRows())

<div class="ds-paper">
    <div class="ptop">
        <div>
            <div class="ds-logo">
                <div class="ds-mark"><span class="d">D</span><span>S</span></div>
                <div class="ds-slash"></div>
                <div>
                    <div class="w1">DYNAMIC</div>
                    <div class="w2">SOLUTIONS</div>
                </div>
            </div>
            <div class="tagline">Fueling your business. Powering your journey.</div>
        </div>

        <div style="min-width:150px">
            <div class="dt">{{ $document->doc_type->documentTitle() }}</div>
            {{-- motif 3 of 3 — the slash beneath the document title --}}
            <div class="dtr"></div>
            <div class="pfrom">
                <strong>{{ $business['registered_name'] ?: $business['trading_name'] }}</strong><br>
                {{ $business['address_line1'] }}<br>
                {{ $business['address_line2'] }}<br>
                Mobile: {{ $business['phone'] }}
                @if ($business['reg_number'] !== '')
                    <br>Reg: {{ $business['reg_number'] }}
                @endif
                @if ($business['vat_number'] !== '')
                    <br>VAT: {{ $business['vat_number'] }}
                @endif
            </div>
        </div>
    </div>

    <hr>

    <div class="pmeta">
        <div>
            <div class="plbl">Bill to</div>
            <div class="pname">{{ $document->bill_to['company'] ?? '—' }}</div>
            <div class="paddr">
                @if (! empty($document->bill_to['contact_name']))
                    {{ $document->bill_to['contact_name'] }}<br>
                @endif
                @if (! empty($document->bill_to['address']))
                    {!! nl2br(e($document->bill_to['address'])) !!}<br>
                @endif
                @if (! empty($document->bill_to['phone']))
                    {{ $document->bill_to['phone'] }}<br>
                @endif
                @if (! empty($document->bill_to['vat_number']))
                    VAT: {{ $document->bill_to['vat_number'] }}
                @endif
            </div>
        </div>

        <div>
            <div class="mr">
                <div class="mk">{{ $document->doc_type->label() }} number:</div>
                <div class="mv">{{ $document->number }}</div>
            </div>
            <div class="mr">
                <div class="mk">Date issued:</div>
                <div class="mv">{{ $document->issued_on->format('j M Y') }}</div>
            </div>
            @if ($document->valid_until)
                <div class="mr vv">
                    <div class="mk">Valid until:</div>
                    <div class="mv">{{ $document->valid_until->format('j M Y') }}</div>
                </div>
            @endif
            @if ($document->due_on)
                <div class="mr vv">
                    <div class="mk">Payment due:</div>
                    <div class="mv">{{ $document->due_on->format('j M Y') }}</div>
                </div>
            @endif
            <div class="mr">
                <div class="mk">Fulfilment:</div>
                <div class="mv">{{ Str::title($document->fulfilment) }}</div>
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
            @foreach ($document->lines as $line)
                <tr>
                    <td>
                        @if ($line->code)<b>{{ $line->code }}</b><br>@endif
                        <span style="color:var(--color-ds-steel)">{{ $line->description }}</span>
                    </td>
                    <td>{{ $line->litres !== null ? litres($line->litres).' L' : '1' }}</td>
                    <td>{{ $line->price_per_litre !== null ? money((int) \App\Support\Money::toCents($line->price_per_litre)) : '—' }}</td>
                    <td>{{ money($line->amount_cents) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="pt">
        <div class="ptb">
            @if ($showsVat)
                <div class="ptr">
                    <div class="k">Subtotal</div>
                    <div class="v">{{ money($document->subtotal_cents) }}</div>
                </div>
                <div class="ptr">
                    <div class="k">VAT @ {{ rtrim(rtrim(number_format((float) $document->vat_rate * 100, 2), '0'), '.') }}%</div>
                    <div class="v">{{ money($document->vat_cents) }}</div>
                </div>
            @endif
            <div class="grand">
                <div class="k">TOTAL (ZAR)</div>
                <div class="v">{{ money($document->total_cents) }}</div>
            </div>
        </div>
    </div>

    @if ($document->notes)
        <div style="margin-top:18px">
            <h5 style="font-size:8px;letter-spacing:.16em;text-transform:uppercase;color:var(--color-ds-mute);font-weight:600;margin:0 0 5px">
                Notes
            </h5>
            <div style="font-size:9px;line-height:1.62;color:var(--color-ds-steel);white-space:pre-line">{{ $document->notes }}</div>
        </div>
    @endif

    <div class="pcols">
        <div>
            <h5>Banking details</h5>
            <div class="body mono">{{ $document->banking }}</div>
        </div>
        <div>
            <h5>Terms</h5>
            <div class="body">{{ $document->terms }}</div>
        </div>
    </div>

    <div class="pfoot">TRUSTED FUEL. <em>DRIVEN BY SERVICE.</em></div>
</div>
