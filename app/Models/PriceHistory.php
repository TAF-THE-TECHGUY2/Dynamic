<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One published price for one grade, from one moment onwards.
 *
 * Append only. Publishing Wednesday's price inserts a row; it never updates
 * Tuesday's. That is what makes it possible to answer "why does this quote say
 * R23.70?" six months later.
 *
 * @property string $id
 * @property string $grade_id
 * @property string $price_per_litre
 * @property bool $vat_inclusive
 * @property Carbon $effective_from
 * @property string|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $publisher
 */
#[Table('price_history')]
#[Fillable(['grade_id', 'price_per_litre', 'vat_inclusive', 'effective_from', 'created_by'])]
class PriceHistory extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'vat_inclusive' => 'boolean',
            'effective_from' => 'datetime',
            // Deliberately not cast to float. Prices travel as decimal strings
            // all the way into App\Support\Totals.
            'price_per_litre' => 'string',
        ];
    }

    /** @return BelongsTo<Grade, $this> */
    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    /** @return BelongsTo<User, $this> */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
