<?php

declare(strict_types=1);

use App\Models\PriceHistory;
use App\Models\User;
use App\Services\PriceBook;
use Carbon\CarbonImmutable as Carbon;
use Database\Seeders\GradeSeeder;
use Database\Seeders\SettingSeeder;

beforeEach(function () {
    $this->seed([GradeSeeder::class, SettingSeeder::class]);
});

it('shows the published price on the homepage immediately after publishing', function () {
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)
        ->post('/admin/pricing', ['prices' => ['50ppm' => '23.70', '500ppm' => '23.20']])
        ->assertRedirect();

    $this->get('/')->assertOk()->assertSee('R23.70', escape: false);

    // Publishing again must reach the public page without waiting out the
    // 60-second page cache.
    $this->actingAs($owner)
        ->post('/admin/pricing', ['prices' => ['50ppm' => '24.95', '500ppm' => '23.20']])
        ->assertRedirect();

    $this->get('/')
        ->assertOk()
        ->assertSee('R24.95', escape: false)
        ->assertDontSee('R23.70', escape: false);
});

it('keeps the previous price in history rather than updating it in place', function () {
    $owner = User::factory()->owner()->create();
    $book = app(PriceBook::class);

    $book->publish(['50ppm' => '23.70'], $owner);
    $book->publish(['50ppm' => '24.95'], $owner);

    $rows = PriceHistory::query()->where('grade_id', '50ppm')->orderBy('created_at')->get();

    expect($rows)->toHaveCount(2)
        ->and($rows[0]->price_per_litre)->toBe('23.7000')
        ->and($rows[1]->price_per_litre)->toBe('24.9500')
        ->and($book->priceFor('50ppm'))->toBe('24.9500');
});

it('does not append a row when the price has not actually moved', function () {
    $owner = User::factory()->owner()->create();
    $book = app(PriceBook::class);

    $book->publish(['50ppm' => '23.70'], $owner);
    $book->publish(['50ppm' => '23.70'], $owner);

    expect(PriceHistory::query()->where('grade_id', '50ppm')->count())->toBe(1);
});

it('refuses to let an admin publish a price', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post('/admin/pricing', ['prices' => ['50ppm' => '99.99']])
        ->assertForbidden();

    expect(PriceHistory::query()->count())->toBe(0);
});

it('refuses to let an attendant near pricing at all', function () {
    $attendant = User::factory()->attendant()->create();

    $this->actingAs($attendant)->get('/admin/pricing')->assertForbidden();
    $this->actingAs($attendant)->post('/admin/pricing', ['prices' => ['50ppm' => '1']])->assertForbidden();
});

it('lets the owner see the pricing screen with its full history', function () {
    $owner = User::factory()->owner()->create();

    app(PriceBook::class)->publish(['50ppm' => '23.70'], $owner);

    $this->actingAs($owner)
        ->get('/admin/pricing')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/pricing/index')
            ->where('canPublish', true)
            ->has('history', 1));
});

it('makes a published price visible immediately, whatever the sub-second timing', function () {
    $owner = User::factory()->owner()->create();

    // The visibility guard in PriceBook::current() must compare against the
    // application clock. Postgres now() is transaction time — frozen before
    // this write — and on Forge the database runs UTC while the app runs SAST.
    // Either one hides a price the owner has just published.
    Carbon::setTestNow(Carbon::parse('2026-08-12 12:00:00.900'));

    app(PriceBook::class)->publish(['50ppm' => '23.70'], $owner);

    expect(app(PriceBook::class)->priceFor('50ppm'))->toBe('23.7000');

    $this->get('/')->assertOk()->assertSee('R23.70', escape: false);

    Carbon::setTestNow();
});
