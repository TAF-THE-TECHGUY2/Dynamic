<?php

declare(strict_types=1);

use App\Enums\DocStatus;
use App\Mail\ContactMessage;
use App\Mail\PublicEstimateForBusiness;
use App\Mail\PublicEstimateForCustomer;
use App\Models\Document;
use App\Models\User;
use App\Services\PriceBook;
use App\Services\Settings;
use Database\Seeders\GradeSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->seed([GradeSeeder::class, SettingSeeder::class]);

    $this->owner = User::factory()->owner()->create();

    app(Settings::class)->setMany([
        'business.vat_number' => '4123456789',
        'business.email' => 'yard@dynamicsolutions.test',
    ]);

    app(PriceBook::class)->publish(['50ppm' => '23.70', '500ppm' => '23.20'], $this->owner);
});

it('prices an estimate through the same code that prices a real document', function () {
    $this->postJson('/quote/calculate', ['grade_id' => '50ppm', 'litres' => '12000'])
        ->assertOk()
        ->assertJson([
            'available' => true,
            'total_display' => 'R284,400.00',
            'subtotal_display' => 'R247,304.35',
            'vat_display' => 'R37,095.65',
            'shows_vat' => true,
        ]);
});

it('flags an order below the delivery minimum', function () {
    app(Settings::class)->set('min_order_litres', 500);

    $this->postJson('/quote/calculate', ['grade_id' => '50ppm', 'litres' => '100'])
        ->assertOk()
        ->assertJson(['available' => true, 'below_minimum' => true, 'minimum_litres' => 500]);
});

it('rejects an estimate below the minimum order', function () {
    app(Settings::class)->set('min_order_litres', 500);

    $this->post('/quote', [
        'grade_id' => '50ppm',
        'litres' => '100',
        'fulfilment' => 'delivery',
        'name' => 'Johan Kruger',
        'phone' => '082 445 1190',
        'email' => 'johan@kruger.test',
    ])->assertSessionHasErrors('litres');

    expect(Document::query()->count())->toBe(0);
});

it('lands a website estimate in the admin console as a draft and emails both sides', function () {
    Mail::fake();

    $this->post('/quote', [
        'grade_id' => '50ppm',
        'litres' => '12000',
        'fulfilment' => 'delivery',
        'suburb' => 'Chloorkop, Kempton Park',
        'name' => 'Johan Kruger',
        'company' => 'Kruger Haulage',
        'phone' => '082 445 1190',
        'email' => 'johan@kruger.test',
    ])->assertRedirect();

    $document = Document::query()->firstOrFail();

    expect($document->status)->toBe(DocStatus::Draft)
        ->and($document->total_cents)->toBe(28_440_000)
        ->and($document->bill_to['company'])->toBe('Kruger Haulage')
        ->and($document->public_token)->not->toBeNull();

    Mail::assertQueued(PublicEstimateForCustomer::class);
    Mail::assertQueued(PublicEstimateForBusiness::class);

    // It shows up in the admin list.
    $this->actingAs($this->owner)
        ->get('/admin/documents')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('documents.data', 1));
});

it('renders the public link for a logged-out visitor', function () {
    Mail::fake();

    $this->post('/quote', [
        'grade_id' => '50ppm',
        'litres' => '12000',
        'fulfilment' => 'collection',
        'name' => 'Johan Kruger',
        'phone' => '082 445 1190',
        'email' => 'johan@kruger.test',
    ]);

    $document = Document::query()->firstOrFail();

    $this->get("/d/{$document->public_token}")
        ->assertOk()
        ->assertSee($document->number)
        ->assertSee('R284,400.00', escape: false)
        ->assertSee('TRUSTED FUEL.', escape: false);
});

it('shows an expiry banner on a lapsed quote and offers a re-price instead', function () {
    Mail::fake();

    $this->post('/quote', [
        'grade_id' => '50ppm',
        'litres' => '12000',
        'fulfilment' => 'delivery',
        'name' => 'Johan Kruger',
        'phone' => '082 445 1190',
        'email' => 'johan@kruger.test',
    ]);

    $document = Document::query()->firstOrFail();
    $document->update(['valid_until' => today()->subDay(), 'status' => DocStatus::Expired]);

    $this->get("/d/{$document->public_token}")
        ->assertOk()
        ->assertSee('This quote has expired')
        ->assertSee('Ask for a fresh quote');
});

it('does not expose a document without a token', function () {
    $this->get('/d/not-a-real-token')->assertNotFound();
});

it('accepts a contact message', function () {
    Mail::fake();

    $this->post('/contact', [
        'name' => 'Johan Kruger',
        'phone' => '082 445 1190',
        'email' => 'johan@kruger.test',
        'message' => 'Do you deliver to Springs?',
    ])->assertRedirect()->assertSessionHas('contact.sent');

    Mail::assertQueued(ContactMessage::class);
});

it('renders every public page', function (string $path) {
    $this->get($path)->assertOk();
})->with(['/', '/bulk-diesel', '/truck-stop', '/quote', '/contact']);

it('puts the price in the server HTML with its timestamp and caveat', function () {
    $response = $this->get('/');

    $response->assertOk()
        ->assertSee('R23.70', escape: false)
        ->assertSee('SUBJECT TO CHANGE')
        ->assertSee('INCL. VAT');

    // The value must be in the markup, not filled in by JavaScript afterwards.
    expect($response->content())->toContain('pumpCounter(23.7)');
});
