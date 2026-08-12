<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Dec;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A truck in the yard. Open while check_out is null.
 *
 * @property string $id
 * @property string|null $customer_id
 * @property string|null $company
 * @property string|null $vehicle_reg
 * @property string|null $bay
 * @property Carbon $check_in
 * @property Carbon|null $check_out
 * @property string|null $rate_per_day
 * @property string|null $notes
 * @property-read Customer|null $customer
 */
#[Fillable([
    'customer_id', 'company', 'vehicle_reg', 'bay', 'check_in', 'check_out', 'rate_per_day', 'notes',
])]
class YardBooking extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'check_in' => 'datetime',
            'check_out' => 'datetime',
            'rate_per_day' => 'string',
        ];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function isOpen(): bool
    {
        return $this->check_out === null;
    }

    /** Part days count as whole days, which is how the yard actually bills. */
    public function daysStayed(): int
    {
        $until = $this->check_out ?? now();

        return max(1, (int) ceil($this->check_in->diffInHours($until) / 24));
    }

    public function chargeCents(): int
    {
        if ($this->rate_per_day === null) {
            return 0;
        }

        return (int) bcmul(
            Dec::scaleToInt($this->rate_per_day, 2),
            (string) $this->daysStayed(),
            0,
        );
    }

    /** @param  Builder<YardBooking>  $query */
    #[Scope]
    protected function open(Builder $query): void
    {
        $query->whereNull('check_out');
    }
}
