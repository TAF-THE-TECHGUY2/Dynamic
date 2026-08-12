<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    /** Everything, including publishing prices and changing settings. */
    case Owner = 'owner';

    /**
     * Issue and edit documents, manage customers, check trucks in.
     * Cannot publish prices or change settings.
     */
    case Admin = 'admin';

    /** Yard bookings only. Cannot see pricing, documents or customers. */
    case Attendant = 'attendant';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function description(): string
    {
        return match ($this) {
            self::Owner => 'Owner · full access',
            self::Admin => 'Admin · documents and customers',
            self::Attendant => 'Attendant · yard only',
        };
    }

    public function isOwner(): bool
    {
        return $this === self::Owner;
    }

    /** Documents, customers and pricing are off-limits to the yard attendant. */
    public function seesSales(): bool
    {
        return $this !== self::Attendant;
    }
}
