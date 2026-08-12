<x-mail::message>
# {{ $document->doc_type->label() }} {{ $document->number }}

Please find your {{ Str::lower($document->doc_type->label()) }} attached.

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

@if ($document->valid_until)
This quote is valid until **{{ $document->valid_until->format('j M Y') }}**. Diesel reprices regularly, so after that date we will need to re-quote.
@endif
@if ($document->due_on)
Payment is due by **{{ $document->due_on->format('j M Y') }}**. Please use **{{ $document->number }}** as your reference.
@endif

@if ($publicUrl)
<x-mail::button :url="$publicUrl">View it online</x-mail::button>
@endif

Thanks,<br>
{{ settings('business.trading_name', 'Dynamic Solutions') }}
{{ settings('business.phone') }}
</x-mail::message>
