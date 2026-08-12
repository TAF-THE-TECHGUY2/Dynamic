<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line on a quote or invoice.
 *
 * price_per_litre is a snapshot taken when the document was created, not a
 * reference to today's price. That is the whole point of the column.
 *
 * @property string $id
 * @property string $document_id
 * @property string|null $grade_id
 * @property string|null $code
 * @property string $description
 * @property string|null $litres
 * @property string|null $price_per_litre
 * @property int $amount_cents
 * @property int $sort
 */
#[Fillable([
    'grade_id', 'code', 'description', 'litres', 'price_per_litre', 'amount_cents', 'sort',
])]
class DocumentLine extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'sort' => 'integer',
            // Decimal strings, never floats — see App\Support\Totals.
            'litres' => 'string',
            'price_per_litre' => 'string',
        ];
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /** A flat charge — delivery, a standing fee — rather than litres at a rate. */
    public function isFlatCharge(): bool
    {
        return $this->litres === null && $this->price_per_litre === null;
    }
}
