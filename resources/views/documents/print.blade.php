<!DOCTYPE html>
{{--
    The print and PDF surface.

    Rendered by the browser at /admin/documents/{document}/print and by
    headless Chromium through spatie/laravel-pdf, so what the owner sees on
    screen is byte-for-byte what the customer receives.

    @param \App\Models\Document $document
--}}
<html lang="en-ZA">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $document->number }} — Dynamic Solutions</title>
    <meta name="robots" content="noindex, nofollow">

    @if ($inlineAssets ?? false)
        {{-- Browsershot renders from a temp file and cannot fetch the app's
             assets over HTTP, so the compiled stylesheet and the brand fonts
             are inlined for the PDF path only. --}}
        <style>{!! \App\Support\InlineAssets::forPdf() !!}</style>
    @else
        @vite(['resources/css/app.css'])
    @endif

    <style>
        @page {
            size: A4;
            margin: 12mm;
        }

        html, body {
            background: #fff;
            margin: 0;
            padding: 0;
        }

        /* On screen the paper sits on a grey desk. In print it is the page. */
        .print-desk {
            background: var(--color-ds-mist);
            min-height: 100vh;
            padding: 24px 16px;
        }

        .print-sheet {
            background: #fff;
            max-width: 190mm;
            margin: 0 auto;
            border: 1px solid var(--color-ds-line);
        }

        .print-bar {
            max-width: 190mm;
            margin: 0 auto 14px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
        }

        @media print {
            .print-desk {
                background: #fff;
                padding: 0;
                min-height: 0;
            }

            .print-sheet {
                border: 0;
                max-width: none;
            }

            .print-bar {
                display: none;
            }

            /* Chromium drops background colours by default, and the red header
               band and black total band are not decoration — they are how the
               document is read. */
            * {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body class="font-body">
    <div class="print-desk">
        <div class="print-bar">
            <a class="ds-btn ds-btn-ghost" href="{{ route('admin.documents.edit', $document) }}">← Back to builder</a>
            <button type="button" class="ds-btn ds-btn-red" onclick="window.print()">Print</button>
        </div>

        <div class="print-sheet">
            @include('documents.paper', ['document' => $document])
        </div>
    </div>
</body>
</html>
