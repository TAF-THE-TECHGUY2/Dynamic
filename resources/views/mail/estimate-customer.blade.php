<x-mail::message>
# Your diesel estimate

Thanks {{ $document->bill_to['contact_name'] ?? 'there' }} — here is what that load works out to at today's published rate.

<x-mail::table>
| Item | Litres | Rate | Amount |
|:-----|-------:|-----:|-------:|
@foreach ($document->lines as $line)
| {{ $line->description }} | {{ $line->litres ? litres($line->litres).' L' : '1' }} | {{ $line->price_per_litre ? money((int) \App\Support\Money::toCents($line->price_per_litre)) : '—' }} | {{ money($line->amount_cents) }} |
@endforeach
</x-mail::table>

**Total (ZAR): {{ money($document->total_cents) }}**
@if ($document->vat_mode->showsVatRows())
Includes VAT of {{ money($document->vat_cents) }}.
@endif

This is an **estimate** at the rate published on {{ $document->issued_on->format('j M Y') }}, not a formal quote. Diesel reprices regularly, so we will confirm a quote with a validity date before anything is delivered.

@if ($publicUrl)
<x-mail::button :url="$publicUrl">View your estimate</x-mail::button>
@endif

Reference: **{{ $document->number }}**

Questions? Call or WhatsApp {{ settings('business.phone') }}.

Thanks,<br>
{{ settings('business.trading_name', 'Dynamic Solutions') }}
</x-mail::message>
