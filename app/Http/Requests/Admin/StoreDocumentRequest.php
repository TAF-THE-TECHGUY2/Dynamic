<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\DocType;
use App\Enums\VatMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a document coming out of the builder.
 *
 * Note what is not here: totals. The builder posts line data only. Any
 * subtotal, VAT or total in the payload is ignored — App\Support\Totals
 * recomputes them server-side and the stored value wins.
 */
final class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // The controller authorises against DocumentPolicy.
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'doc_type' => ['required', Rule::enum(DocType::class)],
            'customer_id' => ['nullable', 'uuid', Rule::exists('customers', 'id')],

            'company' => ['required', 'string', 'max:160'],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:160'],
            'vat_number' => ['nullable', 'string', 'max:40'],
            'delivery_address' => ['nullable', 'string', 'max:500'],

            'issued_on' => ['required', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:issued_on'],
            'due_on' => ['nullable', 'date', 'after_or_equal:issued_on'],
            'fulfilment' => ['required', Rule::in(['delivery', 'collection'])],
            'vat_mode' => ['required', Rule::enum(VatMode::class)],
            'notes' => ['nullable', 'string', 'max:2000'],

            'lines' => ['required', 'array', 'min:1', 'max:40'],
            'lines.*.grade_id' => ['nullable', 'string', Rule::exists('grades', 'id')],
            'lines.*.code' => ['nullable', 'string', 'max:40'],
            'lines.*.description' => ['required', 'string', 'max:200'],

            // Decimal strings, matching the column scales. Rejected rather than
            // silently truncated so nobody is surprised by a rounded litre.
            'lines.*.litres' => ['nullable', 'numeric', 'min:-9999999999', 'max:9999999999'],
            'lines.*.price_per_litre' => ['nullable', 'numeric', 'min:0', 'max:999999.9999'],
            'lines.*.amount_cents' => ['nullable', 'integer'],

            'autosave' => ['nullable', 'boolean'],
        ];
    }

    /**
     * The shape App\Services\DocumentWriter expects.
     *
     * @return array<string, mixed>
     */
    public function documentData(): array
    {
        $validated = $this->validated();

        return [
            ...$validated,
            'lines' => array_map(fn (array $line) => [
                'grade_id' => $line['grade_id'] ?? null,
                'code' => $line['code'] ?? null,
                'description' => $line['description'],
                'litres' => isset($line['litres']) ? (string) $line['litres'] : null,
                'price_per_litre' => isset($line['price_per_litre']) ? (string) $line['price_per_litre'] : null,
                'amount_cents' => (int) ($line['amount_cents'] ?? 0),
            ], $validated['lines']),
        ];
    }
}
