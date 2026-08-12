<?php

declare(strict_types=1);

use App\Enums\VatMode;
use App\Support\Totals;

/**
 * tests/fixtures/totals.json is read by this suite and by Vitest
 * (resources/js/lib/totals.test.ts). The PHP implementation is authoritative
 * and the TypeScript one drives the builder's live preview; if they drift
 * apart, one of these two suites fails and CI stops the build.
 */
function totalsFixtures(): array
{
    $path = dirname(__DIR__).'/fixtures/totals.json';

    $cases = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

    return collect($cases)->keyBy('name')->map(fn (array $case) => [$case])->all();
}

dataset('totals fixtures', totalsFixtures());

it('matches the shared fixture', function (array $case) {
    $result = Totals::compute(
        $case['lines'],
        VatMode::from($case['vat_mode']),
        $case['vat_rate'],
    );

    expect($result->subtotalCents)->toBe($case['expect']['subtotal_cents'])
        ->and($result->vatCents)->toBe($case['expect']['vat_cents'])
        ->and($result->totalCents)->toBe($case['expect']['total_cents'])
        ->and($result->lineAmountsCents)->toBe($case['expect']['line_amounts_cents']);
})->with('totals fixtures');

it('always has the line amounts add up to the subtotal or the total', function (array $case) {
    $result = Totals::compute(
        $case['lines'],
        VatMode::from($case['vat_mode']),
        $case['vat_rate'],
    );

    $sum = array_sum($result->lineAmountsCents);

    // Inclusive pricing sums to the total; the other two sum to the subtotal.
    $expected = $case['vat_mode'] === 'inclusive' ? $result->totalCents : $result->subtotalCents;

    expect($sum)->toBe($expected);
})->with('totals fixtures');

it('always has subtotal plus VAT equal the total', function (array $case) {
    $result = Totals::compute(
        $case['lines'],
        VatMode::from($case['vat_mode']),
        $case['vat_rate'],
    );

    expect($result->subtotalCents + $result->vatCents)->toBe($result->totalCents);
})->with('totals fixtures');

it('accepts the acceptance-criteria load from the build brief', function () {
    $result = Totals::compute(
        [['litres' => '12000', 'price_per_litre' => '23.7000']],
        VatMode::Inclusive,
    );

    expect($result->subtotalCents)->toBe(24_730_435)
        ->and($result->vatCents)->toBe(3_709_565)
        ->and($result->totalCents)->toBe(28_440_000)
        ->and(money($result->totalCents))->toBe('R284,400.00');
});

it('rounds each line before summing, not the other way round', function () {
    // Three lines of exactly half a cent. Rounded per line they are 1c each and
    // sum to 3c. Summed first they would be 1.5c and round to 2c — which is the
    // bug this rule exists to prevent, because the line amounts are printed
    // individually and have to add up on the page.
    $halfCent = ['litres' => '1.00', 'price_per_litre' => '0.0050'];

    $result = Totals::compute([$halfCent, $halfCent, $halfCent], VatMode::None);

    expect($result->lineAmountsCents)->toBe([1, 1, 1])
        ->and($result->totalCents)->toBe(3);
});

it('never returns a float', function () {
    $result = Totals::compute(
        [['litres' => '1234.56', 'price_per_litre' => '23.7000']],
        VatMode::Inclusive,
    );

    expect($result->subtotalCents)->toBeInt()
        ->and($result->vatCents)->toBeInt()
        ->and($result->totalCents)->toBeInt()
        ->and($result->lineAmountsCents[0])->toBeInt();
});

it('rejects a value that is not a decimal number', function () {
    Totals::compute([['litres' => '12,000', 'price_per_litre' => '23.70']], VatMode::None);
})->throws(InvalidArgumentException::class);
