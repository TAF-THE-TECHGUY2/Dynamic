<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The output of Totals::compute(). Every figure is an integer number of cents.
 */
final readonly class TotalsResult
{
    /**
     * @param  array<int, int>  $lineAmountsCents  one rounded amount per input line, in order
     */
    public function __construct(
        public int $subtotalCents,
        public int $vatCents,
        public int $totalCents,
        public array $lineAmountsCents,
    ) {}

    /**
     * @return array{subtotal_cents: int, vat_cents: int, total_cents: int, line_amounts_cents: array<int, int>}
     */
    public function toArray(): array
    {
        return [
            'subtotal_cents' => $this->subtotalCents,
            'vat_cents' => $this->vatCents,
            'total_cents' => $this->totalCents,
            'line_amounts_cents' => $this->lineAmountsCents,
        ];
    }
}
