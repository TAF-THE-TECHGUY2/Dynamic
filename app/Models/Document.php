<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DocStatus;
use App\Enums\DocType;
use App\Enums\VatMode;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A quote, pro forma or invoice.
 *
 * Everything a document prints is snapshotted onto the row at creation:
 * the customer details into bill_to, the price onto each line, and the terms
 * and banking text into their own columns.
 *
 * @property string $id
 * @property DocType $doc_type
 * @property string $number
 * @property int $seq
 * @property int $seq_year
 * @property string|null $customer_id
 * @property array<string, string|null> $bill_to
 * @property Carbon $issued_on
 * @property Carbon|null $valid_until
 * @property Carbon|null $due_on
 * @property string $fulfilment
 * @property VatMode $vat_mode
 * @property string $vat_rate
 * @property int $subtotal_cents
 * @property int $vat_cents
 * @property int $total_cents
 * @property DocStatus $status
 * @property string|null $notes
 * @property string $terms
 * @property string $banking
 * @property string|null $converted_from
 * @property string|null $public_token
 * @property string|null $pdf_path
 * @property Carbon|null $sent_at
 * @property Carbon|null $paid_at
 * @property string $created_by
 * @property-read Collection<int, DocumentLine> $lines
 * @property-read Customer|null $customer
 * @property-read Document|null $convertedFrom
 */
#[Fillable([
    'doc_type', 'number', 'seq', 'seq_year', 'customer_id', 'bill_to', 'issued_on',
    'valid_until', 'due_on', 'fulfilment', 'vat_mode', 'vat_rate', 'subtotal_cents',
    'vat_cents', 'total_cents', 'status', 'notes', 'terms', 'banking', 'converted_from',
    'public_token', 'pdf_path', 'sent_at', 'paid_at', 'created_by',
])]
class Document extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'doc_type' => DocType::class,
            'status' => DocStatus::class,
            'vat_mode' => VatMode::class,
            'bill_to' => 'array',
            'issued_on' => 'date',
            'valid_until' => 'date',
            'due_on' => 'date',
            'sent_at' => 'datetime',
            'paid_at' => 'datetime',
            'subtotal_cents' => 'integer',
            'vat_cents' => 'integer',
            'total_cents' => 'integer',
            'seq' => 'integer',
            'seq_year' => 'integer',
            'vat_rate' => 'string',
        ];
    }

    /** @return HasMany<DocumentLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(DocumentLine::class)->orderBy('sort');
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<Document, $this> */
    public function convertedFrom(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'converted_from');
    }

    /** @return HasMany<Document, $this> */
    public function conversions(): HasMany
    {
        return $this->hasMany(Document::class, 'converted_from');
    }

    /**
     * Everything this document needs in order to be rendered.
     *
     * NEVER join to price_history, customers or settings from here, or from the
     * views this feeds. Those tables hold today's values; this document holds
     * the values it was issued with. Publishing Wednesday's diesel price must
     * not change the total on a quote sent last week — including one the
     * customer has already accepted — and a customer renaming their company
     * must not rewrite the address on an invoice already issued.
     *
     * It looks like a reasonable optimisation to reach for the live record.
     * It is not. It is a silent repricing of documents that have already left
     * the building.
     *
     * @return array<string, mixed>
     */
    public function forRender(): array
    {
        return [
            'number' => $this->number,
            'doc_type' => $this->doc_type,
            'title' => $this->doc_type->documentTitle(),
            'bill_to' => $this->bill_to,
            'issued_on' => $this->issued_on,
            'valid_until' => $this->valid_until,
            'due_on' => $this->due_on,
            'fulfilment' => $this->fulfilment,
            'vat_mode' => $this->vat_mode,
            'vat_rate' => $this->vat_rate,
            'subtotal_cents' => $this->subtotal_cents,
            'vat_cents' => $this->vat_cents,
            'total_cents' => $this->total_cents,
            'notes' => $this->notes,
            'terms' => $this->terms,
            'banking' => $this->banking,
            'lines' => $this->lines,
            'status' => $this->status,
        ];
    }

    /** Total litres across the priced lines, for list and dashboard columns. */
    public function totalLitres(): string
    {
        $total = '0';

        foreach ($this->lines as $line) {
            // Flat-charge lines (delivery, standing fees) carry no litres.
            if ($line->litres === null || ! is_numeric($line->litres)) {
                continue;
            }

            $total = bcadd($total, $line->litres, 2);
        }

        return $total;
    }

    public function isExpired(): bool
    {
        return $this->status === DocStatus::Expired;
    }

    /** A quote past its validity window, whether or not the scheduler has caught it yet. */
    public function hasLapsed(): bool
    {
        return $this->doc_type->expires()
            && $this->valid_until !== null
            && $this->valid_until->endOfDay()->isPast();
    }

    public function isOverdue(): bool
    {
        return $this->doc_type->isPayable()
            && $this->status !== DocStatus::Paid
            && $this->status !== DocStatus::Cancelled
            && $this->due_on !== null
            && $this->due_on->endOfDay()->isPast();
    }

    public function publicUrl(): ?string
    {
        return $this->public_token === null
            ? null
            : route('documents.public', $this->public_token);
    }

    /** @param  Builder<Document>  $query */
    #[Scope]
    protected function ofType(Builder $query, DocType $type): void
    {
        $query->where('doc_type', $type->value);
    }

    /** @param  Builder<Document>  $query */
    #[Scope]
    protected function outstanding(Builder $query): void
    {
        $query->where('doc_type', DocType::Invoice->value)
            ->whereNotIn('status', [DocStatus::Paid->value, DocStatus::Cancelled->value]);
    }
}
