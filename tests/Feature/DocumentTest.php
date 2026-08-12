<?php

declare(strict_types=1);

use App\Enums\DocStatus;
use App\Enums\DocType;
use App\Enums\VatMode;
use App\Models\Customer;
use App\Models\Document;
use App\Models\User;
use App\Services\DocumentWriter;
use App\Services\PriceBook;
use App\Services\Settings;
use Database\Seeders\GradeSeeder;
use Database\Seeders\SettingSeeder;

beforeEach(function () {
    $this->seed([GradeSeeder::class, SettingSeeder::class]);

    $this->owner = User::factory()->owner()->create();

    // Documents only show VAT when a VAT number is configured.
    app(Settings::class)->set('business.vat_number', '4123456789');

    app(PriceBook::class)->publish(['50ppm' => '23.70', '500ppm' => '23.20'], $this->owner);
});

function quotePayload(array $overrides = []): array
{
    return array_replace([
        'doc_type' => 'quote',
        'company' => 'Kruger Haulage (Pty) Ltd',
        'contact_name' => 'Johan Kruger',
        'phone' => '082 445 1190',
        'email' => 'johan@kruger.test',
        'delivery_address' => "Unit 4, Chloorkop Industrial\nKempton Park, Gauteng",
        'issued_on' => today()->toDateString(),
        'fulfilment' => 'delivery',
        'vat_mode' => 'inclusive',
        'lines' => [[
            'grade_id' => '50ppm',
            'code' => 'D50-BULK',
            'description' => 'Diesel 50ppm',
            'litres' => '12000',
            'price_per_litre' => '23.7000',
        ]],
    ], $overrides);
}

it('totals a 12,000 litre quote at R23.70 inclusive to R284,400.00', function () {
    $this->actingAs($this->owner)
        ->post('/admin/documents', quotePayload())
        ->assertRedirect();

    $document = Document::query()->latest('created_at')->firstOrFail();

    expect($document->subtotal_cents)->toBe(24_730_435)
        ->and($document->vat_cents)->toBe(3_709_565)
        ->and($document->total_cents)->toBe(28_440_000)
        ->and(money($document->total_cents))->toBe('R284,400.00')
        ->and($document->number)->toStartWith('DS-')
        ->and($document->status)->toBe(DocStatus::Draft);
});

it('ignores any total posted from the client', function () {
    // A tampered payload claiming the load is worth ten rand.
    $this->actingAs($this->owner)
        ->post('/admin/documents', quotePayload([
            'subtotal_cents' => 1000,
            'vat_cents' => 0,
            'total_cents' => 1000,
            'lines' => [[
                'grade_id' => '50ppm',
                'code' => 'D50-BULK',
                'description' => 'Diesel 50ppm',
                'litres' => '12000',
                'price_per_litre' => '23.7000',
                'amount_cents' => 1000,
            ]],
        ]))
        ->assertRedirect();

    $document = Document::query()->latest('created_at')->firstOrFail();

    expect($document->total_cents)->toBe(28_440_000)
        ->and($document->lines->first()->amount_cents)->toBe(28_440_000);
});

it('does not change a saved quote when the diesel price moves', function () {
    $this->actingAs($this->owner)->post('/admin/documents', quotePayload());

    $document = Document::query()->latest('created_at')->firstOrFail();
    $originalTotal = $document->total_cents;

    // Wednesday's new price.
    app(PriceBook::class)->publish(['50ppm' => '26.40'], $this->owner);

    $document->refresh()->load('lines');

    expect($document->total_cents)->toBe($originalTotal)
        ->and($document->total_cents)->toBe(28_440_000)
        ->and($document->lines->first()->price_per_litre)->toBe('23.7000');
});

it('does not change an issued document when the customer record is renamed', function () {
    $customer = Customer::query()->create([
        'company' => 'Kruger Haulage (Pty) Ltd',
        'delivery_address' => 'Unit 4, Chloorkop Industrial',
    ]);

    $this->actingAs($this->owner)
        ->post('/admin/documents', quotePayload(['customer_id' => $customer->id]));

    $document = Document::query()->latest('created_at')->firstOrFail();

    $this->actingAs($this->owner)->patch("/admin/customers/{$customer->id}", [
        'company' => 'Kruger Logistics Group',
        'delivery_address' => 'Somewhere else entirely',
    ]);

    expect($document->refresh()->bill_to['company'])->toBe('Kruger Haulage (Pty) Ltd')
        ->and($document->bill_to['address'])->toBe("Unit 4, Chloorkop Industrial\nKempton Park, Gauteng");
});

it('does not change an issued document when the banking details change', function () {
    $this->actingAs($this->owner)->post('/admin/documents', quotePayload());

    $document = Document::query()->latest('created_at')->firstOrFail();
    $bankingAtIssue = $document->banking;

    app(Settings::class)->setMany([
        'banking.bank' => 'A Completely Different Bank',
        'banking.account_number' => '999999999',
    ]);

    expect($document->refresh()->banking)->toBe($bankingAtIssue)
        ->and($document->banking)->not->toContain('999999999');
});

it('defaults a quote to expire after the configured window', function () {
    app(Settings::class)->set('quote_valid_hours', 48);

    $this->actingAs($this->owner)->post('/admin/documents', quotePayload());

    $document = Document::query()->latest('created_at')->firstOrFail();

    expect($document->valid_until->toDateString())
        ->toBe(today()->addHours(48)->toDateString());
});

it('expires quotes past their validity date', function () {
    $this->actingAs($this->owner)->post('/admin/documents', quotePayload([
        'issued_on' => today()->subDays(10)->toDateString(),
        'valid_until' => today()->subDays(8)->toDateString(),
    ]));

    $document = Document::query()->latest('created_at')->firstOrFail();
    $document->update(['status' => DocStatus::Sent]);

    $this->artisan('documents:expire')->assertSuccessful();

    expect($document->refresh()->status)->toBe(DocStatus::Expired);
});

it('copies a quote into an invoice rather than mutating it', function () {
    $this->actingAs($this->owner)->post('/admin/documents', quotePayload());

    $quote = Document::query()->latest('created_at')->firstOrFail();

    $this->actingAs($this->owner)
        ->post("/admin/documents/{$quote->id}/convert")
        ->assertRedirect();

    $invoice = Document::query()->where('doc_type', DocType::Invoice->value)->firstOrFail();

    expect($invoice->number)->toStartWith('INV-')
        ->and($invoice->converted_from)->toBe($quote->id)
        ->and($invoice->total_cents)->toBe($quote->total_cents)
        ->and($invoice->lines->first()->price_per_litre)->toBe('23.7000')
        ->and($invoice->due_on)->not->toBeNull()
        // The quote survives, readable, marked accepted.
        ->and($quote->refresh()->status)->toBe(DocStatus::Accepted)
        ->and($quote->doc_type)->toBe(DocType::Quote)
        ->and($quote->total_cents)->toBe(28_440_000);
});

it('re-prices an expired quote at today\'s rate and leaves the original intact', function () {
    $this->actingAs($this->owner)->post('/admin/documents', quotePayload());

    $original = Document::query()->latest('created_at')->firstOrFail();
    $original->update(['status' => DocStatus::Expired]);

    app(PriceBook::class)->publish(['50ppm' => '26.40'], $this->owner);

    $this->actingAs($this->owner)
        ->post("/admin/documents/{$original->id}/reprice")
        ->assertRedirect();

    $fresh = Document::query()->where('id', '!=', $original->id)->latest('created_at')->firstOrFail();

    expect($fresh->number)->not->toBe($original->number)
        ->and($fresh->lines->first()->price_per_litre)->toBe('26.4000')
        ->and($fresh->total_cents)->toBe(31_680_000)
        // The original is untouched.
        ->and($original->refresh()->total_cents)->toBe(28_440_000)
        ->and($original->lines->first()->price_per_litre)->toBe('23.7000');
});

it('forces VAT mode to none when no VAT number is configured', function () {
    app(Settings::class)->set('business.vat_number', '');

    $this->actingAs($this->owner)
        ->post('/admin/documents', quotePayload(['vat_mode' => 'inclusive']));

    $document = Document::query()->latest('created_at')->firstOrFail();

    // Showing a VAT line without a VAT number is not compliant with SARS
    // requirements, so the request is overridden rather than trusted.
    expect($document->vat_mode)->toBe(VatMode::None)
        ->and($document->vat_cents)->toBe(0)
        ->and($document->subtotal_cents)->toBe(28_440_000)
        ->and($document->total_cents)->toBe(28_440_000);
});

it('never shows a VAT row on a document issued without a VAT number', function () {
    app(Settings::class)->set('business.vat_number', '');

    $this->actingAs($this->owner)->post('/admin/documents', quotePayload());

    $document = Document::query()->latest('created_at')->firstOrFail();

    $this->actingAs($this->owner)
        ->get("/admin/documents/{$document->id}/print")
        ->assertOk()
        ->assertDontSee('VAT @', escape: false);
});

it('autosaves a draft without shouting about it', function () {
    $this->actingAs($this->owner)->post('/admin/documents', quotePayload());

    $document = Document::query()->latest('created_at')->firstOrFail();

    $this->actingAs($this->owner)
        ->patch("/admin/documents/{$document->id}", quotePayload([
            'autosave' => true,
            'lines' => [[
                'grade_id' => '50ppm',
                'code' => 'D50-BULK',
                'description' => 'Diesel 50ppm',
                'litres' => '15000',
                'price_per_litre' => '23.7000',
            ]],
        ]))
        ->assertRedirect()
        ->assertSessionMissing('success');

    expect($document->refresh()->total_cents)->toBe(35_550_000);
});

it('keeps an attendant out of documents entirely', function () {
    $attendant = User::factory()->attendant()->create();

    $this->actingAs($attendant)->get('/admin/documents')->assertForbidden();
    $this->actingAs($attendant)->post('/admin/documents', quotePayload())->assertForbidden();
});

it('gives each document type its own number sequence', function () {
    $writer = app(DocumentWriter::class);

    $quote = $writer->create(quotePayload(), $this->owner);
    $invoice = $writer->create(quotePayload(['doc_type' => 'invoice']), $this->owner);

    expect($quote->number)->toBe(sprintf('DS-%d-0001', now()->year))
        ->and($invoice->number)->toBe(sprintf('INV-%d-0001', now()->year));
});
