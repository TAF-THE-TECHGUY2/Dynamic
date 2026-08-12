<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DocType;
use Illuminate\Support\Facades\DB;

/**
 * Hands out the next document number for a type and year.
 *
 * A row lock inside a transaction, never max(seq) + 1 in application code: two
 * staff clicking "new quote" in the same second would otherwise both read the
 * same value and one of the two documents would end up overwriting the other's
 * number, or failing the unique index in front of a customer.
 *
 * Quotes and invoices number independently — DS-2026-0185 sits alongside
 * INV-2026-0091.
 */
final class DocumentNumberer
{
    public function next(DocType $type, ?int $year = null): DocumentNumber
    {
        return DB::transaction(function () use ($type, $year) {
            $year ??= now()->year;

            // Make sure the row exists before we try to lock it. lockForUpdate
            // on a row that is not there locks nothing, so two callers starting
            // in the same fresh year would both take the "insert" branch and one
            // would hit the primary key. insertOrIgnore blocks on the unique
            // index instead and then quietly does nothing.
            DB::table('doc_counters')->insertOrIgnore([
                'doc_type' => $type->value,
                'year' => $year,
                'last_seq' => 0,
            ]);

            $counter = DB::table('doc_counters')
                ->where('doc_type', $type->value)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            $seq = ((int) $counter->last_seq) + 1;

            DB::table('doc_counters')
                ->where('doc_type', $type->value)
                ->where('year', $year)
                ->update(['last_seq' => $seq]);

            return new DocumentNumber(
                sprintf('%s-%d-%04d', $type->prefix(), $year, $seq),
                $seq,
                $year,
            );
        });
    }

    /**
     * What the next number will look like, without consuming it.
     *
     * Used only to show a placeholder in the builder before the draft is saved.
     * The number a document actually keeps always comes from next().
     */
    public function peek(DocType $type, ?int $year = null): string
    {
        $year ??= now()->year;

        $lastSeq = (int) (DB::table('doc_counters')
            ->where('doc_type', $type->value)
            ->where('year', $year)
            ->value('last_seq') ?? 0);

        return sprintf('%s-%d-%04d', $type->prefix(), $year, $lastSeq + 1);
    }
}
