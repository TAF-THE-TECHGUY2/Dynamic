@extends('layouts.site')

@section('title', $document->doc_type->label().' '.$document->number.' | Dynamic Solutions')
@section('description', 'Your '.Str::lower($document->doc_type->label()).' from Dynamic Solutions.')

@push('head')
    {{-- A customer's document is not for a search index. --}}
    <meta name="robots" content="noindex, nofollow">
@endpush

@section('content')
    <div class="ds-sec">
        @if ($expired)
            <div class="ds-banner" style="margin-bottom:20px">
                <b>This quote has expired</b>
                It was valid until {{ $document->valid_until?->format('j M Y') }}. Diesel reprices
                regularly, so the figures below are no longer available. Call
                {{ settings('business.phone') }} or ask us for a fresh quote and we will re-price it
                at today's rate.
            </div>
        @elseif ($document->valid_until)
            <div class="ds-banner warn" style="margin-bottom:20px">
                <b>Valid until {{ $document->valid_until->format('j M Y') }}</b>
                Diesel reprices regularly. Accept before that date and this rate stands.
            </div>
        @endif

        <div class="ds-eyebrow">{{ $document->doc_type->label() }}</div>
        <h2 class="ds-num" style="font-family:var(--font-mono);font-size:26px;letter-spacing:-.01em">
            {{ $document->number }}
        </h2>

        <div class="ds-prev" style="margin-top:22px;position:static">
            <div class="ds-prevbar">
                <div class="t">{{ $document->doc_type->documentTitle() }} · A4</div>
                <div class="t" style="color:var(--color-ds-red)">
                    {{ Str::upper($document->status->label()) }}
                </div>
            </div>

            @include('documents.paper', ['document' => $document])

            <div class="ds-prevact">
                <a class="ds-btn ds-btn-red"
                   href="{{ whatsapp_link(
                       ($expired
                           ? 'Hi Dynamic Solutions, please re-price '
                           : 'Hi Dynamic Solutions, I would like to accept ')
                       .$document->number.'.'
                   ) }}"
                   rel="noopener" target="_blank">
                    {{ $expired ? 'Ask for a fresh quote' : 'Accept on WhatsApp' }}
                </a>
                <a class="ds-btn ds-btn-dark"
                   href="tel:{{ preg_replace('/\s+/', '', settings('business.phone', '')) }}">
                    Call {{ settings('business.phone') }}
                </a>
            </div>
        </div>
    </div>
@endsection
