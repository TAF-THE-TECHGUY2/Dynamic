<?php

declare(strict_types=1);

namespace App\Services;

final readonly class DocumentNumber
{
    public function __construct(
        public string $number,
        public int $seq,
        public int $year,
    ) {}

    public function __toString(): string
    {
        return $this->number;
    }
}
