<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\DocStatus;
use App\Enums\DocType;
use App\Enums\VatMode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreDocumentRequest;
use App\Jobs\RenderDocumentPdf;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Grade;
use App\Services\DocumentNumberer;
use App\Services\DocumentWriter;
use App\Services\PriceBook;
use App\Services\Settings;
use App\Support\DocumentPresenter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DocumentController extends Controller
{
    public function __construct(
        private readonly DocumentWriter $writer,
        private readonly DocumentNumberer $numberer,
        private readonly PriceBook $prices,
        private readonly Settings $settings,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Document::class);

        $filters = $request->validate([
            'type' => ['nullable', 'string'],
            'status' => ['nullable', 'string'],
            'search' => ['nullable', 'string', 'max:120'],
        ]);

        $documents = Document::query()
            ->with('lines')
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('doc_type', $type))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(
                fn ($q) => $q->where('number', 'ilike', "%{$search}%")
                    ->orWhereRaw("bill_to->>'company' ilike ?", ["%{$search}%"]),
            ))
            ->orderByDesc('issued_on')
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('admin/documents/index', [
            'documents' => $documents->through(DocumentPresenter::forList(...)),
            'filters' => $filters,
            'metrics' => DocumentPresenter::listMetrics(),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Document::class);

        $type = DocType::tryFrom((string) $request->query('type')) ?? DocType::Quote;

        return Inertia::render('admin/documents/builder', [
            ...$this->builderProps(),
            'document' => null,
            'placeholderNumber' => $this->numberer->peek($type),
            'defaultType' => $type->value,
        ]);
    }

    public function edit(Document $document): Response
    {
        $this->authorize('view', $document);

        return Inertia::render('admin/documents/builder', [
            ...$this->builderProps(),
            'document' => DocumentPresenter::forBuilder($document->load('lines')),
            'placeholderNumber' => $document->number,
            'defaultType' => $document->doc_type->value,
        ]);
    }

    public function store(StoreDocumentRequest $request): RedirectResponse
    {
        $this->authorize('create', Document::class);

        $document = $this->writer->create($request->documentData(), $request->user());

        return redirect()
            ->route('admin.documents.edit', $document)
            ->with('success', "{$document->number} saved as a draft.");
    }

    /**
     * Also the autosave endpoint: the builder patches here after two seconds
     * idle, so nobody loses a half-built quote to a closed tab.
     */
    public function update(StoreDocumentRequest $request, Document $document): RedirectResponse
    {
        $this->authorize('update', $document);

        $this->writer->update($document, $request->documentData());

        return back()->with('success', $request->boolean('autosave') ? null : 'Saved.');
    }

    public function destroy(Document $document): RedirectResponse
    {
        $this->authorize('delete', $document);

        $number = $document->number;
        $document->delete();

        return redirect()
            ->route('admin.documents.index')
            ->with('success', "{$number} deleted.");
    }

    /**
     * Blade, not Inertia — the same template Browsershot renders, so the print
     * preview and the PDF cannot drift apart.
     */
    public function print(Document $document): View
    {
        $this->authorize('view', $document);

        return view('documents.print', ['document' => $document->load('lines')]);
    }

    /**
     * Hand back the stored PDF through a temporary signed URL, or render one on
     * demand if the document has not been issued yet.
     */
    public function pdf(Document $document): RedirectResponse|StreamedResponse
    {
        $this->authorize('view', $document);

        $disk = Storage::disk(config('filesystems.default'));

        if ($document->pdf_path !== null && $disk->exists($document->pdf_path)) {
            // Never a public bucket URL — a signed URL that expires.
            return $disk->providesTemporaryUrls()
                ? redirect()->away($disk->temporaryUrl($document->pdf_path, now()->addMinutes(5)))
                : $disk->download($document->pdf_path, $document->number.'.pdf');
        }

        return response()->streamDownload(
            fn () => print (RenderDocumentPdf::render($document)),
            $document->number.'.pdf',
            ['Content-Type' => 'application/pdf'],
        );
    }

    /**
     * Issue the document: give it a public link, mark it sent, then queue the
     * PDF render, the S3 upload and the email.
     */
    public function issue(Document $document): RedirectResponse
    {
        $this->authorize('issue', $document);

        $this->writer->markSent($document);

        RenderDocumentPdf::dispatch($document->id, sendEmail: true);

        return back()->with('success', "{$document->number} issued. The PDF and email are on their way.");
    }

    // The parameter has to be named $document to match the {document} route
    // segment, or Laravel hands the method a brand new empty model instead of
    // resolving the binding.
    public function convert(Request $request, Document $document): RedirectResponse
    {
        $this->authorize('convert', $document);

        $invoice = $this->writer->convertToInvoice($document, $request->user());

        return redirect()
            ->route('admin.documents.edit', $invoice)
            ->with('success', "{$document->number} accepted and invoiced as {$invoice->number}.");
    }

    public function reprice(Request $request, Document $document): RedirectResponse
    {
        $this->authorize('reprice', $document);

        $fresh = $this->writer->reprice($document, $request->user(), $this->prices);

        return redirect()
            ->route('admin.documents.edit', $fresh)
            ->with('success', "Re-priced at today's rate as {$fresh->number}. {$document->number} is untouched.");
    }

    public function status(Request $request, Document $document): RedirectResponse
    {
        $this->authorize('update', $document);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:draft,sent,accepted,paid,expired,cancelled'],
        ]);

        $status = DocStatus::from($validated['status']);

        $document->update([
            'status' => $status,
            'paid_at' => $status === DocStatus::Paid ? ($document->paid_at ?? now()) : null,
        ]);

        return back()->with('success', "{$document->number} marked {$status->label()}.");
    }

    /** @return array<string, mixed> */
    private function builderProps(): array
    {
        $current = $this->prices->current();

        return [
            'grades' => Grade::query()->active()->get()->map(fn (Grade $grade) => [
                'id' => $grade->id,
                'label' => $grade->label,
                'code' => $grade->code,
                'price_per_litre' => $current->get($grade->id)['price_per_litre'] ?? null,
            ])->values(),
            'customers' => Customer::query()
                ->orderBy('company')
                ->get(['id', 'company', 'contact_name', 'phone', 'email', 'vat_number', 'delivery_address']),
            'business' => $this->settings->businessBlock(),
            'terms' => [
                'quote' => $this->settings->termsFor('quote'),
                'proforma' => $this->settings->termsFor('proforma'),
                'invoice' => $this->settings->termsFor('invoice'),
            ],
            'banking' => $this->settings->bankingBlock(),
            'vatRegistered' => $this->settings->isVatRegistered(),
            'vatRate' => $this->settings->vatRate(),
            'defaultVatMode' => ($this->settings->isVatRegistered() ? VatMode::Inclusive : VatMode::None)->value,
            'quoteValidHours' => $this->settings->quoteValidHours(),
            'invoiceDueDays' => $this->settings->int('invoice_due_days', 30),
        ];
    }
}
