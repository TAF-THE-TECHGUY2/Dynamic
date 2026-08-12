<x-mail::message>
# New website estimate

Someone has asked for a diesel estimate on the website. It is sitting in the admin console as a **draft** — nothing has been issued.

**{{ $document->bill_to['company'] ?? 'Website visitor' }}**
{{ $document->bill_to['contact_name'] }} · {{ $document->bill_to['phone'] }}
{{ $document->bill_to['email'] }}
@if ($document->bill_to['address'])
Delivery to: {{ $document->bill_to['address'] }}
@endif

<x-mail::table>
| Item | Litres | Rate | Amount |
|:-----|-------:|-----:|-------:|
@foreach ($document->lines as $line)
| {{ $line->description }} | {{ $line->litres ? litres($line->litres).' L' : '1' }} | {{ $line->price_per_litre ? money((int) \App\Support\Money::toCents($line->price_per_litre)) : '—' }} | {{ money($line->amount_cents) }} |
@endforeach
</x-mail::table>

**Total: {{ money($document->total_cents) }}** · Fulfilment: {{ $document->fulfilment }}

<x-mail::button :url="$adminUrl">Open the draft</x-mail::button>

{{ $document->number }}
</x-mail::message>
