<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A diesel grade. The primary key is the grade's own name ('50ppm') rather than
 * a surrogate, so a price row and a document line both read as English.
 *
 * @property string $id
 * @property string $label
 * @property string $code
 * @property string|null $blurb
 * @property int $sort
 * @property bool $active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['id', 'label', 'code', 'blurb', 'sort', 'active'])]
class Grade extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'sort' => 'integer',
        ];
    }

    /** @return HasMany<PriceHistory, $this> */
    public function prices(): HasMany
    {
        return $this->hasMany(PriceHistory::class);
    }

    /** @param  Builder<Grade>  $query */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('active', true)->orderBy('sort');
    }
}
