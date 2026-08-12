<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Grade;
use App\Models\PriceHistory;
use App\Models\User;
use App\Support\PageCache;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Today's diesel price, and the act of publishing a new one.
 *
 * Publishing inserts a row into price_history and never updates one in place,
 * so the history stays auditable and a quote issued last week can always be
 * explained. Documents snapshot the price they were issued at; nothing here is
 * ever consulted when rendering one.
 */
final class PriceBook
{
    private const CACHE_KEY = 'pricebook.current';

    /**
     * The latest published price for each active grade.
     *
     * @return Collection<string, array{grade: Grade, price_per_litre: string, vat_inclusive: bool, effective_from: Carbon}>
     */
    public function current(): Collection
    {
        /** @var array<string, array{grade_id: string, price_per_litre: string, vat_inclusive: bool, effective_from: string}> $rows */
        $rows = Cache::remember(self::CACHE_KEY, PageCache::TTL_SECONDS, function () {
            // One row per grade: the most recent price that has already taken
            // effect. DISTINCT ON is Postgres doing in one pass what a
            // correlated subquery would do in several.
            //
            // The id tiebreaker matters. effective_from is a second-precision
            // timestamp, so correcting a price a moment after publishing it
            // gives two rows the same instant and the winner would otherwise be
            // whichever one the planner happened to hand back first. Model ids
            // are UUID v7, which sort in creation order.
            return DB::table('price_history')
                ->select('id', 'grade_id', 'price_per_litre', 'vat_inclusive', 'effective_from')
                // Bound from PHP, never Postgres's now(). Two reasons, both of
                // which hide a price the owner has just published:
                //
                //   · Postgres now() is *transaction* time, so inside any
                //     transaction it is frozen at the moment the transaction
                //     opened — which can be before the price was written.
                //   · Forge provisions Postgres in UTC while this app runs in
                //     Africa/Johannesburg, so the two clocks read two hours
                //     apart and every new price would be invisible until the
                //     database caught up.
                ->where('effective_from', '<=', now())
                ->orderBy('grade_id')
                ->orderByDesc('effective_from')
                ->orderByDesc('id')
                ->distinct('grade_id')
                ->get()
                ->keyBy('grade_id')
                ->map(fn ($row) => (array) $row)
                ->all();
        });

        return Grade::query()
            ->active()
            ->get()
            ->mapWithKeys(function (Grade $grade) use ($rows) {
                $row = $rows[$grade->id] ?? null;

                if ($row === null) {
                    return [];
                }

                return [$grade->id => [
                    'grade' => $grade,
                    'price_per_litre' => (string) $row['price_per_litre'],
                    'vat_inclusive' => (bool) $row['vat_inclusive'],
                    'effective_from' => Carbon::parse($row['effective_from']),
                ]];
            });
    }

    /**
     * The same information as current(), flattened to plain scalars.
     *
     * This is what the public pages render and what PageCache stores. Eloquent
     * models must never go into the cache: the `database` store serialises what
     * it is given, and a model coming back out is both stale and awkward to
     * unserialize.
     *
     * @return array{grades: array<int, array<string, mixed>>, last_changed_at: string|null}
     */
    public function display(): array
    {
        $current = $this->current();

        return [
            'grades' => $current->map(fn (array $row) => [
                'id' => $row['grade']->id,
                'label' => $row['grade']->label,
                'code' => $row['grade']->code,
                'blurb' => $row['grade']->blurb,
                'price_per_litre' => $row['price_per_litre'],
                'vat_inclusive' => $row['vat_inclusive'],
                'effective_from' => $row['effective_from']->toIso8601String(),
            ])->values()->all(),
            'last_changed_at' => $this->lastChangedAt()?->toIso8601String(),
        ];
    }

    /** The price to default a new document line to. Null when nothing is published yet. */
    public function priceFor(string $gradeId): ?string
    {
        return $this->current()->get($gradeId)['price_per_litre'] ?? null;
    }

    /** When the price last moved — always rendered next to the price itself. */
    public function lastChangedAt(): ?Carbon
    {
        return $this->current()->max('effective_from');
    }

    /**
     * Publish new prices. One appended row per grade whose price actually moved.
     *
     * @param  array<string, string>  $pricesByGrade  grade id => decimal string
     * @return Collection<int, PriceHistory>
     */
    public function publish(array $pricesByGrade, User $publisher, ?Carbon $effectiveFrom = null): Collection
    {
        $effectiveFrom ??= now();
        $currentPrices = $this->current();

        $published = DB::transaction(function () use ($pricesByGrade, $publisher, $effectiveFrom, $currentPrices) {
            $rows = collect();

            foreach ($pricesByGrade as $gradeId => $price) {
                $normalised = number_format((float) $price, 4, '.', '');
                $existing = $currentPrices->get($gradeId);

                // Republishing an unchanged price would add noise to the history
                // without telling anyone anything.
                if ($existing !== null && $existing['price_per_litre'] === $normalised) {
                    continue;
                }

                $rows->push(PriceHistory::query()->create([
                    'grade_id' => $gradeId,
                    'price_per_litre' => $normalised,
                    'vat_inclusive' => true,
                    'effective_from' => $effectiveFrom,
                    'created_by' => $publisher->id,
                ]));
            }

            return $rows;
        });

        $this->flush();

        return $published;
    }

    /** @return Collection<int, PriceHistory> */
    public function history(int $limit = 60): Collection
    {
        return PriceHistory::query()
            ->with(['grade', 'publisher'])
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);

        // The homepage prints the price. A publish has to reach it immediately,
        // not in up to sixty seconds.
        PageCache::bump();
    }
}
