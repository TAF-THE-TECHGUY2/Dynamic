<?php

declare(strict_types=1);

namespace App\Enums;

enum DocType: string
{
    case Quote = 'quote';
    case Proforma = 'proforma';
    case Invoice = 'invoice';

    /** Quotes and invoices number independently: DS-2026-0185 alongside INV-2026-0091. */
    public function prefix(): string
    {
        return match ($this) {
            self::Invoice => 'INV',
            self::Proforma => 'PF',
            self::Quote => 'DS',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Quote => 'Quote',
            self::Proforma => 'Pro forma',
            self::Invoice => 'Tax invoice',
        };
    }

    /** The word printed at the top right of the document itself. */
    public function documentTitle(): string
    {
        return match ($this) {
            self::Quote => 'QUOTE',
            self::Proforma => 'PRO FORMA',
            self::Invoice => 'TAX INVOICE',
        };
    }

    public function expires(): bool
    {
        return $this === self::Quote;
    }

    public function isPayable(): bool
    {
        return $this === self::Invoice;
    }
}
