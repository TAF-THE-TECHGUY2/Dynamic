<?php

declare(strict_types=1);

use App\Models\PriceHistory;
use App\Models\User;
use App\Services\PriceBook;
use Database\Seeders\GradeSeeder;
use Database\Seeders\SettingSeeder;

beforeEach(function () {
    $this->seed([GradeSeeder::class, SettingSeeder::class]);

    $this->owner = User::factory()->owner()->create();
});

it('publishes a price from the command line', function () {
    $this->artisan('ds:publish-price', ['prices' => ['50ppm=23.70'], '--force' => true])
        ->assertSuccessful();

    expect(app(PriceBook::class)->priceFor('50ppm'))->toBe('23.7000');
});

it('publishes several grades at once', function () {
    $this->artisan('ds:publish-price', [
        'prices' => ['50ppm=23.70', '500ppm=23.20'],
        '--force' => true,
    ])->assertSuccessful();

    $book = app(PriceBook::class);

    expect($book->priceFor('50ppm'))->toBe('23.7000')
        ->and($book->priceFor('500ppm'))->toBe('23.2000');
});

it('appends to the history rather than updating in place', function () {
    $this->artisan('ds:publish-price', ['prices' => ['50ppm=23.70'], '--force' => true]);
    $this->artisan('ds:publish-price', ['prices' => ['50ppm=24.95'], '--force' => true]);

    $rows = PriceHistory::query()->where('grade_id', '50ppm')->orderBy('created_at')->get();

    expect($rows)->toHaveCount(2)
        ->and($rows[0]->price_per_litre)->toBe('23.7000')
        ->and($rows[1]->price_per_litre)->toBe('24.9500');
});

it('makes the new price live on the homepage immediately', function () {
    $this->artisan('ds:publish-price', ['prices' => ['50ppm=23.70'], '--force' => true]);

    $this->get('/')->assertOk()->assertSee('R23.70', escape: false);
});

it('records who published it', function () {
    $this->artisan('ds:publish-price', [
        'prices' => ['50ppm=23.70'],
        '--as' => $this->owner->email,
        '--force' => true,
    ])->assertSuccessful();

    expect(PriceHistory::query()->first()->created_by)->toBe($this->owner->id);
});

it('refuses to let a non-owner publish', function () {
    $admin = User::factory()->admin()->create();

    $this->artisan('ds:publish-price', [
        'prices' => ['50ppm=23.70'],
        '--as' => $admin->email,
        '--force' => true,
    ])->assertFailed();

    expect(PriceHistory::query()->count())->toBe(0);
});

it('rejects a fat-fingered price that would be off by a factor of a hundred', function () {
    // 2370 instead of 23.70 would reprice every new quote a hundredfold.
    $this->artisan('ds:publish-price', ['prices' => ['50ppm=2370'], '--force' => true])
        ->assertFailed();

    expect(PriceHistory::query()->count())->toBe(0);
});

it('rejects an unknown grade', function () {
    $this->artisan('ds:publish-price', ['prices' => ['70ppm=23.70'], '--force' => true])
        ->assertFailed();

    expect(PriceHistory::query()->count())->toBe(0);
});

it('rejects something that is not a price at all', function () {
    $this->artisan('ds:publish-price', ['prices' => ['50ppm=R23,70'], '--force' => true])
        ->assertFailed();

    expect(PriceHistory::query()->count())->toBe(0);
});

it('says nothing changed when the price is already current', function () {
    $this->artisan('ds:publish-price', ['prices' => ['50ppm=23.70'], '--force' => true]);

    $this->artisan('ds:publish-price', ['prices' => ['50ppm=23.70'], '--force' => true])
        ->expectsOutputToContain('No change')
        ->assertSuccessful();

    expect(PriceHistory::query()->where('grade_id', '50ppm')->count())->toBe(1);
});

it('normalises a price to four decimals without going through a float', function () {
    $this->artisan('ds:publish-price', ['prices' => ['50ppm=23.7'], '--force' => true]);

    expect(PriceHistory::query()->first()->price_per_litre)->toBe('23.7000');
});
