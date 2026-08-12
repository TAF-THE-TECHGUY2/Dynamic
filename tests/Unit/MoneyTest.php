<?php

declare(strict_types=1);

use App\Support\Dec;
use App\Support\Money;

it('formats cents as rand', function (int $cents, string $expected) {
    expect(money($cents))->toBe($expected);
})->with([
    [8_225_000, 'R82,250.00'],
    [28_440_000, 'R284,400.00'],
    [0, 'R0.00'],
    [1, 'R0.01'],
    [99, 'R0.99'],
    [100, 'R1.00'],
    [-8_225_000, '-R82,250.00'],
    [2_499_999_975, 'R24,999,999.75'],
]);

it('formats cents without the symbol when asked', function () {
    expect(money(8_225_000, symbol: false))->toBe('82,250.00');
});

it('formats compact rand for metric cards', function (int $cents, string $expected) {
    expect(Money::compact($cents))->toBe($expected);
})->with([
    [437_000_000, 'R4.37m'],
    [61_240_000, 'R612.4k'],
    [24_890_000, 'R248.9k'],
    [500_000, 'R5,000'],
    [0, 'R0'],
]);

it('parses rand strings into exact cents', function (string $rands, int $expected) {
    expect(Money::toCents($rands))->toBe($expected);
})->with([
    ['23.70', 2370],
    ['23.7', 2370],
    ['0.01', 1],
    ['82250.00', 8_225_000],
    ['-12.34', -1234],
    // Half a cent rounds up, exactly as PostgreSQL would on the way into the column.
    ['0.005', 1],
]);

it('round-trips cents through a decimal string', function (int $cents) {
    expect(Money::toCents(Money::toDecimalString($cents)))->toBe($cents);
})->with([0, 1, 99, 100, 8_225_000, -4321]);

it('formats litres with separators and trims empty decimals', function (string $value, string $expected) {
    expect(litres($value))->toBe($expected);
})->with([
    ['12000.00', '12,000'],
    ['12000', '12,000'],
    ['1234.56', '1,234.56'],
    ['1234.50', '1,234.5'],
    ['0', '0'],
    ['999999.99', '999,999.99'],
]);

it('rounds halves towards positive infinity, matching Math.round in the browser', function () {
    expect(Dec::divRoundHalfUp('5', '10'))->toBe('1')     // 0.5 -> 1
        ->and(Dec::divRoundHalfUp('15', '10'))->toBe('2')  // 1.5 -> 2
        ->and(Dec::divRoundHalfUp('-5', '10'))->toBe('0')  // -0.5 -> 0
        ->and(Dec::divRoundHalfUp('-15', '10'))->toBe('-1') // -1.5 -> -1
        ->and(Dec::divRoundHalfUp('-16', '10'))->toBe('-2'); // -1.6 -> -2
});

it('scales decimal strings to exact integers', function (string $value, int $scale, string $expected) {
    expect(Dec::scaleToInt($value, $scale))->toBe($expected);
})->with([
    ['23.70', 4, '237000'],
    ['23.7000', 4, '237000'],
    ['12000', 2, '1200000'],
    ['0', 4, '0'],
    ['-1.00', 2, '-100'],
    ['', 2, '0'],
    // Beyond the column's scale, round half up.
    ['23.70005', 4, '237001'],
    ['1000.005', 2, '100001'],
]);
