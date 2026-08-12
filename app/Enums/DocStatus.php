<?php

declare(strict_types=1);

namespace App\Enums;

enum DocStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Paid = 'paid';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** Maps onto the .ds-pill modifiers ported from the mockup. */
    public function pill(): string
    {
        return match ($this) {
            self::Draft => 'new',
            self::Sent, self::Accepted => 'sent',
            self::Paid => 'paid',
            self::Expired, self::Cancelled => 'exp',
        };
    }

    /** A document that has left the building can no longer be edited freely. */
    public function isIssued(): bool
    {
        return $this !== self::Draft;
    }

    /** Only a live quote can be accepted or turned into an invoice. */
    public function isConvertible(): bool
    {
        return in_array($this, [self::Draft, self::Sent, self::Accepted], true);
    }
}
