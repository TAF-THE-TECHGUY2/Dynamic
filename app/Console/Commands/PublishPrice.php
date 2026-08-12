<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Grade;
use App\Models\PriceHistory;
use App\Models\User;
use App\Services\PriceBook;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Publish today's diesel price from the command line.
 *
 *   php artisan ds:publish-price 50ppm=23.70 500ppm=23.20
 *   php artisan ds:publish-price                 # asks for each active grade
 *
 * Does exactly what the owner does at /admin/pricing: appends a row to
 * price_history, never updates one in place, and flushes the public cache so
 * the homepage and quote calculator pick it up on the next request.
 *
 * Deliberately not a raw INSERT. Writing to price_history directly would skip
 * the cache flush — the price would be live in the database and invisible on
 * the website for up to a minute, with nothing to explain why.
 */
final class PublishPrice extends Command
{
    protected $signature = 'ds:publish-price
        {prices?* : grade=price pairs, e.g. 50ppm=23.70}
        {--as= : email of the user to record as the publisher}
        {--force : skip the confirmation}';

    protected $description = "Publish today's diesel price";

    public function handle(PriceBook $prices): int
    {
        $publisher = $this->resolvePublisher();

        if ($publisher === null) {
            return self::FAILURE;
        }

        $grades = Grade::query()->active()->get();

        if ($grades->isEmpty()) {
            $this->error('No active grades. Run `php artisan db:seed --force` first.');

            return self::FAILURE;
        }

        $requested = $this->argument('prices')
            ? $this->parsePairs((array) $this->argument('prices'), $grades)
            : $this->askForPrices($grades, $prices);

        if ($requested === null) {
            return self::FAILURE;
        }

        if ($requested === []) {
            $this->info('Nothing to publish.');

            return self::SUCCESS;
        }

        $this->showChanges($requested, $prices);

        if (! $this->option('force') && ! $this->confirm('Publish these prices?', true)) {
            $this->info('Cancelled. Nothing was written.');

            return self::SUCCESS;
        }

        $published = $prices->publish($requested, $publisher);

        if ($published->isEmpty()) {
            $this->info('No change — those are already the current prices.');

            return self::SUCCESS;
        }

        $published->each(fn (PriceHistory $row) => $this->line(
            "  published  {$row->grade_id}  R{$row->price_per_litre}"
        ));

        $this->newLine();
        $this->info('Live now on the website, the quote calculator and every new document.');
        $this->comment('Quotes already issued keep the price they were issued at.');

        return self::SUCCESS;
    }

    private function resolvePublisher(): ?User
    {
        $email = $this->option('as');

        $user = $email
            ? User::query()->where('email', $email)->first()
            : User::query()->where('role', 'owner')->orderBy('created_at')->first();

        if ($user === null) {
            $this->error($email
                ? "No user with the email {$email}."
                : 'No owner account found. Run `php artisan db:seed --force` first.');

            return null;
        }

        // price_history.created_by is how "who changed the price, and when" is
        // answered six months later, so it is never left blank.
        if (! $user->can('publish-prices')) {
            $this->error("{$user->email} is a {$user->role->value} and cannot publish prices.");

            return null;
        }

        return $user;
    }

    /**
     * @param  array<int, string>  $pairs
     * @param  Collection<int, Grade>  $grades
     * @return array<string, string>|null
     */
    private function parsePairs(array $pairs, $grades): ?array
    {
        $requested = [];

        foreach ($pairs as $pair) {
            if (! str_contains($pair, '=')) {
                $this->error("Expected grade=price, got [{$pair}].");

                return null;
            }

            [$gradeId, $price] = explode('=', $pair, 2);

            if (! $grades->contains('id', $gradeId)) {
                $this->error("Unknown or inactive grade [{$gradeId}]. Active: {$grades->pluck('id')->join(', ')}");

                return null;
            }

            if (! $this->isSanePrice($price)) {
                return null;
            }

            $requested[$gradeId] = $price;
        }

        return $requested;
    }

    /**
     * @param  Collection<int, Grade>  $grades
     * @return array<string, string>
     */
    private function askForPrices($grades, PriceBook $prices): array
    {
        $current = $prices->current();
        $requested = [];

        foreach ($grades as $grade) {
            $now = $current->get($grade->id)['price_per_litre'] ?? null;

            $answer = $this->ask(
                "{$grade->label} price per litre".($now ? " (currently R{$now})" : ' (none published)'),
                $now,
            );

            if (blank($answer)) {
                continue;
            }

            if (! $this->isSanePrice((string) $answer)) {
                continue;
            }

            $requested[$grade->id] = (string) $answer;
        }

        return $requested;
    }

    private function isSanePrice(string $price): bool
    {
        if (preg_match('/^\d+(\.\d{1,4})?$/', $price) !== 1) {
            $this->error("[{$price}] is not a price. Use digits with up to four decimals, e.g. 23.70");

            return false;
        }

        // A fat-fingered 2370 instead of 23.70 would reprice every new quote by
        // a factor of a hundred, so it is worth refusing rather than warning.
        if ((float) $price <= 0 || (float) $price > 999) {
            $this->error("[{$price}] is outside the plausible range for diesel (0–999 per litre).");

            return false;
        }

        return true;
    }

    /** @param  array<string, string>  $requested */
    private function showChanges(array $requested, PriceBook $prices): void
    {
        $current = $prices->current();

        $this->newLine();
        $this->table(
            ['Grade', 'Current', 'New', 'Change'],
            collect($requested)->map(function (string $price, string $gradeId) use ($current) {
                $now = $current->get($gradeId)['price_per_litre'] ?? null;

                $change = $now === null
                    ? 'first price'
                    : sprintf('%+.4f', (float) $price - (float) $now);

                return [$gradeId, $now ? "R{$now}" : '—', "R{$price}", $change];
            })->values()->all(),
        );
    }
}
