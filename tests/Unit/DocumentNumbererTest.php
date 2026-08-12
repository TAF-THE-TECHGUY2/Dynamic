<?php

declare(strict_types=1);

use App\Enums\DocType;
use App\Services\DocumentNumberer;
use Illuminate\Process\Pool;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Deliberately no RefreshDatabase: the concurrency test below launches separate
 * PHP processes, and they cannot see rows written inside this process's open
 * transaction.
 */
uses(TestCase::class);

beforeEach(function () {
    // The child processes below open their own connections, so the schema has
    // to be really on disk rather than inside this process's transaction.
    if (! Schema::hasTable('doc_counters')) {
        Artisan::call('migrate:fresh', ['--force' => true]);
    }

    DB::table('doc_counters')->delete();
});

afterEach(function () {
    DB::table('doc_counters')->delete();
});

it('numbers documents sequentially within a year', function () {
    $numberer = app(DocumentNumberer::class);

    expect($numberer->next(DocType::Quote, 2026)->number)->toBe('DS-2026-0001')
        ->and($numberer->next(DocType::Quote, 2026)->number)->toBe('DS-2026-0002')
        ->and($numberer->next(DocType::Quote, 2026)->number)->toBe('DS-2026-0003');
});

it('numbers each document type independently', function () {
    $numberer = app(DocumentNumberer::class);

    $numberer->next(DocType::Quote, 2026);
    $numberer->next(DocType::Quote, 2026);

    expect($numberer->next(DocType::Invoice, 2026)->number)->toBe('INV-2026-0001')
        ->and($numberer->next(DocType::Proforma, 2026)->number)->toBe('PF-2026-0001')
        ->and($numberer->next(DocType::Quote, 2026)->number)->toBe('DS-2026-0003');
});

it('restarts the sequence in a new year', function () {
    $numberer = app(DocumentNumberer::class);

    $numberer->next(DocType::Quote, 2026);
    $numberer->next(DocType::Quote, 2026);

    expect($numberer->next(DocType::Quote, 2027)->number)->toBe('DS-2027-0001');
});

it('peeks at the next number without consuming it', function () {
    $numberer = app(DocumentNumberer::class);

    expect($numberer->peek(DocType::Quote, 2026))->toBe('DS-2026-0001')
        ->and($numberer->peek(DocType::Quote, 2026))->toBe('DS-2026-0001')
        ->and($numberer->next(DocType::Quote, 2026)->number)->toBe('DS-2026-0001')
        ->and($numberer->peek(DocType::Quote, 2026))->toBe('DS-2026-0002');
});

it('hands out 20 distinct numbers to 20 concurrent callers', function () {
    $connection = config('database.default');

    // Each child boots the framework from scratch, so it has to be pointed at
    // the same test database this process is using.
    $environment = [
        'APP_ENV' => 'testing',
        'DB_CONNECTION' => $connection,
        'DB_HOST' => config("database.connections.{$connection}.host"),
        'DB_PORT' => (string) config("database.connections.{$connection}.port"),
        'DB_DATABASE' => config("database.connections.{$connection}.database"),
        'DB_USERNAME' => config("database.connections.{$connection}.username"),
        'DB_PASSWORD' => (string) config("database.connections.{$connection}.password"),
        'CACHE_STORE' => 'array',
        'SESSION_DRIVER' => 'array',
    ];

    $results = Process::pool(function (Pool $pool) use ($environment) {
        foreach (range(1, 20) as $ignored) {
            $pool->path(base_path())
                ->env($environment)
                ->timeout(60)
                ->command([PHP_BINARY, 'tests/Support/next-number.php', 'quote']);
        }
    })->start()->wait();

    $numbers = $results->collect()
        ->each(fn ($result) => expect($result->successful())->toBeTrue($result->errorOutput()))
        ->map(fn ($result) => trim($result->output()))
        ->values();

    expect($numbers)->toHaveCount(20)
        ->and($numbers->unique())->toHaveCount(20);

    // Distinct is not enough: they must also be the twenty consecutive numbers,
    // with no gaps burned and none skipped.
    $year = now()->year;

    expect($numbers->sort()->values()->all())->toBe(
        collect(range(1, 20))
            ->map(fn (int $seq) => sprintf('DS-%d-%04d', $year, $seq))
            ->all(),
    );

    expect((int) DB::table('doc_counters')->where('doc_type', 'quote')->value('last_seq'))->toBe(20);
});
