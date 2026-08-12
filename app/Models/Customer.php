<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $company
 * @property string|null $contact_name
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $vat_number
 * @property string|null $delivery_address
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'company', 'contact_name', 'phone', 'email', 'vat_number', 'delivery_address', 'notes',
])]
class Customer extends Model
{
    use HasUuids;

    /** @return HasMany<Document, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /** @return HasMany<YardBooking, $this> */
    public function yardBookings(): HasMany
    {
        return $this->hasMany(YardBooking::class);
    }

    /**
     * The snapshot copied onto a document at issue time.
     *
     * Renaming a customer afterwards must not rewrite the address on an invoice
     * that has already gone out, which is why this is copied rather than joined.
     *
     * @return array<string, string|null>
     */
    public function toBillTo(): array
    {
        return [
            'company' => $this->company,
            'contact_name' => $this->contact_name,
            'phone' => $this->phone,
            'email' => $this->email,
            'vat_number' => $this->vat_number,
            'address' => $this->delivery_address,
        ];
    }
}
