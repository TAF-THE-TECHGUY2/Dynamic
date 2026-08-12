<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DocStatus;
use App\Enums\DocType;
use App\Enums\VatMode;
use App\Models\Document;
use App\Models\DocumentLine;
use App\Models\User;
use App\Support\Totals;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates and updates documents.
 *
 * Everything a document will ever print is snapshotted onto it here: the
 * customer details into bill_to, the price onto each line, the terms and
 * banking text into their own columns. After this point the document is a
 * closed record and nothing in the live tables can change what it says.
 *
 * Totals are always recomputed server-side. The builder's React preview posts
 * line data, never totals; anything that looks like a total arriving from a
 * browser is ignored.
 */
final class DocumentWriter
{
    public function __construct(
        private readonly DocumentNumberer $numberer,
        private readonly Settings $settings,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $author): Document
    {
        return DB::transaction(function () use ($data, $author) {
            $type = $data['doc_type'] instanceof DocType
                ? $data['doc_type']
                : DocType::from($data['doc_type'] ?? DocType::Quote->value);

            $number = $this->numberer->next($type);
            $issuedOn = $this->date($data['issued_on'] ?? null) ?? today();

            $document = new Document([
                'doc_type' => $type,
                'number' => $number->number,
                'seq' => $number->seq,
                'seq_year' => $number->year,
                'customer_id' => $data['customer_id'] ?? null,
                'bill_to' => $this->billTo($data),
                'issued_on' => $issuedOn,
                'valid_until' => $this->validUntil($type, $issuedOn, $data['valid_until'] ?? null),
                'due_on' => $this->dueOn($type, $issuedOn, $data['due_on'] ?? null),
                'fulfilment' => $data['fulfilment'] ?? 'delivery',
                'status' => DocStatus::Draft,
                'notes' => $data['notes'] ?? null,

                // Snapshots. Editing the settings later must not rewrite a
                // document that has already been issued.
                'terms' => $data['terms'] ?? $this->settings->termsFor($type->value),
                'banking' => $data['banking'] ?? $this->settings->bankingBlock(),

                'public_token' => $data['public_token'] ?? null,
                'created_by' => $author->id,
            ]);

            $amounts = $this->applyTotals($document, $data);

            $document->save();
            $this->writeLines($document, $data['lines'] ?? [], $amounts);

            return $document->fresh(['lines']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Document $document, array $data): Document
    {
        return DB::transaction(function () use ($document, $data) {
            $issuedOn = $this->date($data['issued_on'] ?? null) ?? $document->issued_on;

            $document->fill([
                'customer_id' => $data['customer_id'] ?? $document->customer_id,
                'bill_to' => $this->billTo($data, $document->bill_to),
                'issued_on' => $issuedOn,
                'valid_until' => $this->validUntil($document->doc_type, $issuedOn, $data['valid_until'] ?? null),
                'due_on' => $this->dueOn($document->doc_type, $issuedOn, $data['due_on'] ?? null),
                'fulfilment' => $data['fulfilment'] ?? $document->fulfilment,
                'notes' => $data['notes'] ?? $document->notes,
            ]);

            $amounts = $this->applyTotals($document, $data);

            $document->save();
            $this->writeLines($document, $data['lines'] ?? [], $amounts);

            return $document->fresh(['lines']);
        });
    }

    /**
     * Turn an accepted quote into an invoice.
     *
     * A new row with a fresh number and duplicated lines carrying their
     * snapshot prices. The quote is marked accepted and stays readable forever
     * — it is never mutated into an invoice.
     */
    public function convertToInvoice(Document $quote, User $author): Document
    {
        return DB::transaction(function () use ($quote, $author) {
            $number = $this->numberer->next(DocType::Invoice);
            $issuedOn = today();

            $invoice = Document::query()->create([
                'doc_type' => DocType::Invoice,
                'number' => $number->number,
                'seq' => $number->seq,
                'seq_year' => $number->year,
                'customer_id' => $quote->customer_id,
                'bill_to' => $quote->bill_to,
                'issued_on' => $issuedOn,
                'valid_until' => null,
                'due_on' => $issuedOn->copy()->addDays($this->settings->int('invoice_due_days', 30)),
                'fulfilment' => $quote->fulfilment,
                'vat_mode' => $quote->vat_mode,
                'vat_rate' => $quote->vat_rate,

                // Copied, not recomputed: the customer accepted these figures.
                'subtotal_cents' => $quote->subtotal_cents,
                'vat_cents' => $quote->vat_cents,
                'total_cents' => $quote->total_cents,

                'status' => DocStatus::Draft,
                'notes' => $quote->notes,
                'terms' => $this->settings->termsFor(DocType::Invoice->value),
                'banking' => $quote->banking,
                'converted_from' => $quote->id,
                'created_by' => $author->id,
            ]);

            foreach ($quote->lines as $line) {
                $invoice->lines()->create($line->only([
                    'grade_id', 'code', 'description', 'litres', 'price_per_litre', 'amount_cents', 'sort',
                ]));
            }

            $quote->update(['status' => DocStatus::Accepted]);

            return $invoice->fresh(['lines']);
        });
    }

    /**
     * Clone an expired quote at today's price, with a fresh number.
     *
     * The original is left exactly as it was. South African diesel reprices
     * monthly, so re-pricing is a routine act, not an exception.
     */
    public function reprice(Document $quote, User $author, PriceBook $prices): Document
    {
        return DB::transaction(function () use ($quote, $author, $prices) {
            $lines = $quote->lines->map(function (DocumentLine $line) use ($prices) {
                $today = $line->grade_id === null ? null : $prices->priceFor($line->grade_id);

                return [
                    'grade_id' => $line->grade_id,
                    'code' => $line->code,
                    'description' => $line->description,
                    'litres' => $line->litres,
                    'price_per_litre' => $today ?? $line->price_per_litre,
                    'amount_cents' => $line->amount_cents,
                    'sort' => $line->sort,
                ];
            })->all();

            return $this->create([
                'doc_type' => DocType::Quote,
                'customer_id' => $quote->customer_id,
                'bill_to' => $quote->bill_to,
                'issued_on' => today()->toDateString(),
                'fulfilment' => $quote->fulfilment,
                'vat_mode' => $quote->vat_mode->value,
                'notes' => $quote->notes,
                'lines' => $lines,
            ], $author);
        });
    }

    /**
     * Mark a document as sent and give it a public link.
     *
     * Queueing the PDF render and the email is the caller's job — see
     * App\Jobs\RenderDocumentPdf.
     */
    public function markSent(Document $document): Document
    {
        $document->update([
            'status' => DocStatus::Sent,
            'sent_at' => $document->sent_at ?? now(),
            'public_token' => $document->public_token ?? $this->newPublicToken(),
        ]);

        return $document;
    }

    public function newPublicToken(): string
    {
        return Str::lower(Str::random(40));
    }

    /**
     * Recompute the totals and stamp them on the document.
     *
     * This is the only place totals are written. Treat any total arriving from
     * a client as untrusted — the posted value is not even read.
     *
     * @param  array<string, mixed>  $data
     * @return array<int, int> the rounded amount for each line, in order
     */
    private function applyTotals(Document $document, array $data): array
    {
        $vatMode = $this->resolveVatMode($data, $document);
        $vatRate = $this->settings->vatRate();

        $totals = Totals::compute($this->totalsLines($data['lines'] ?? []), $vatMode, $vatRate);

        $document->fill([
            'vat_mode' => $vatMode,
            'vat_rate' => $vatRate,
            'subtotal_cents' => $totals->subtotalCents,
            'vat_cents' => $totals->vatCents,
            'total_cents' => $totals->totalCents,
        ]);

        return $totals->lineAmountsCents;
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, array{litres: string|null, price_per_litre: string|null, amount_cents: int}>
     */
    private function totalsLines(array $lines): array
    {
        return array_values(array_map(fn (array $line) => [
            'litres' => $this->decimalOrNull($line['litres'] ?? null),
            'price_per_litre' => $this->decimalOrNull($line['price_per_litre'] ?? null),
            'amount_cents' => (int) ($line['amount_cents'] ?? 0),
        ], $lines));
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @param  array<int, int>  $amounts  from applyTotals(), never from the client
     */
    private function writeLines(Document $document, array $lines, array $amounts): void
    {
        $document->lines()->delete();

        foreach (array_values($lines) as $index => $line) {
            $document->lines()->create([
                'grade_id' => $line['grade_id'] ?? null,
                'code' => $line['code'] ?? null,
                'description' => $line['description'] ?? 'Diesel',
                'litres' => $this->decimalOrNull($line['litres'] ?? null),
                'price_per_litre' => $this->decimalOrNull($line['price_per_litre'] ?? null),
                'amount_cents' => $amounts[$index] ?? 0,
                'sort' => $index,
            ]);
        }
    }

    /**
     * Without a VAT number there is no VAT, whatever the form asked for.
     *
     * Showing a VAT line without a VAT number is not compliant with SARS
     * requirements, so this overrides the request rather than trusting it.
     *
     * @param  array<string, mixed>  $data
     */
    private function resolveVatMode(array $data, Document $document): VatMode
    {
        if (! $this->settings->isVatRegistered()) {
            return VatMode::None;
        }

        $requested = $data['vat_mode'] ?? $document->vat_mode ?? VatMode::Inclusive;

        return $requested instanceof VatMode ? $requested : VatMode::from((string) $requested);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string|null>|null  $existing
     * @return array<string, string|null>
     */
    private function billTo(array $data, ?array $existing = null): array
    {
        if (isset($data['bill_to']) && is_array($data['bill_to'])) {
            return $data['bill_to'];
        }

        return [
            'company' => $data['company'] ?? $existing['company'] ?? null,
            'contact_name' => $data['contact_name'] ?? $existing['contact_name'] ?? null,
            'phone' => $data['phone'] ?? $existing['phone'] ?? null,
            'email' => $data['email'] ?? $existing['email'] ?? null,
            'vat_number' => $data['vat_number'] ?? $existing['vat_number'] ?? null,
            'address' => $data['delivery_address'] ?? $existing['address'] ?? null,
        ];
    }

    private function validUntil(DocType $type, CarbonInterface $issuedOn, mixed $given): ?CarbonInterface
    {
        if (! $type->expires()) {
            return null;
        }

        return $this->date($given)
            ?? $issuedOn->copy()->addHours($this->settings->quoteValidHours());
    }

    private function dueOn(DocType $type, CarbonInterface $issuedOn, mixed $given): ?CarbonInterface
    {
        if (! $type->isPayable()) {
            return null;
        }

        return $this->date($given)
            ?? $issuedOn->copy()->addDays($this->settings->int('invoice_due_days', 30));
    }

    private function date(mixed $value): ?CarbonInterface
    {
        // The app runs on CarbonImmutable (see AppServiceProvider), so anything
        // touching dates has to accept the interface rather than the concrete
        // mutable class.
        if ($value instanceof CarbonInterface) {
            return $value->copy()->startOfDay();
        }

        return blank($value) ? null : Carbon::parse((string) $value)->startOfDay();
    }

    private function decimalOrNull(mixed $value): ?string
    {
        return blank($value) ? null : (string) $value;
    }
}
