<?php

declare(strict_types=1);

namespace App\Enums;

enum VatMode: string
{
    /** No VAT line at all. Forced when no VAT number is configured. */
    case None = 'none';

    /** Line prices exclude VAT; VAT is added on top of the subtotal. */
    case Exclusive = 'exclusive';

    /** Line prices already include VAT; the subtotal is backed out of the total. */
    case Inclusive = 'inclusive';

    public function label(): string
    {
        return match ($this) {
            self::None => 'No VAT',
            self::Exclusive => 'Add 15% VAT',
            self::Inclusive => 'Price includes 15% VAT',
        };
    }

    public function showsVatRows(): bool
    {
        return $this !== self::None;
    }
}
