<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\DocStatus;
use App\Enums\DocType;
use App\Models\Customer;
use App\Models\User;
use App\Models\YardBooking;
use App\Services\DocumentWriter;
use App\Services\PriceBook;
use Illuminate\Database\Seeder;

/**
 * Sample data for local development only.
 *
 * Deliberately NOT part of DatabaseSeeder — running this in production would
 * publish a diesel price the owner never set. Run it explicitly:
 *
 *     php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command->error('DemoSeeder is for local development only.');

            return;
        }

        $owner = User::query()->where('role', 'owner')->firstOrFail();
        $writer = app(DocumentWriter::class);

        // Opening prices, matching design/mockup.html.
        app(PriceBook::class)->publish(['50ppm' => '23.70', '500ppm' => '23.20'], $owner);

        $customers = collect([
            ['company' => 'Kruger Haulage (Pty) Ltd', 'contact_name' => 'Johan Kruger', 'phone' => '082 445 1190', 'email' => 'johan@krugerhaulage.test', 'delivery_address' => "Unit 4, Chloorkop Industrial\nKempton Park, Gauteng"],
            ['company' => 'Metroplan Civils', 'contact_name' => 'Sipho Ndlovu', 'phone' => '083 220 7741', 'email' => 'sipho@metroplan.test', 'delivery_address' => "Site 12, Rietfontein Road\nBenoni, Gauteng"],
            ['company' => 'Northmead Transport', 'contact_name' => 'Riaan Botha', 'phone' => '079 118 3320', 'email' => 'riaan@northmead.test', 'delivery_address' => "9 Kelvin Street\nNorthmead, Benoni"],
            ['company' => 'Bosveld Boerdery', 'contact_name' => 'Anna van Wyk', 'phone' => '072 664 9012', 'email' => 'anna@bosveld.test', 'delivery_address' => "Plot 44, Bapsfontein\nGauteng"],
        ])->map(fn (array $attributes) => Customer::query()->create($attributes));

        $documents = [
            ['customer' => 0, 'type' => DocType::Quote, 'litres' => '12000', 'price' => '23.7000', 'grade' => '50ppm', 'status' => DocStatus::Draft, 'days' => 0],
            ['customer' => 1, 'type' => DocType::Quote, 'litres' => '6500', 'price' => '23.7000', 'grade' => '50ppm', 'status' => DocStatus::Sent, 'days' => 2],
            ['customer' => 2, 'type' => DocType::Invoice, 'litres' => '15000', 'price' => '23.7000', 'grade' => '50ppm', 'status' => DocStatus::Sent, 'days' => 5],
            ['customer' => 3, 'type' => DocType::Quote, 'litres' => '9000', 'price' => '23.2000', 'grade' => '500ppm', 'status' => DocStatus::Expired, 'days' => 13],
            ['customer' => 2, 'type' => DocType::Invoice, 'litres' => '3500', 'price' => '23.5000', 'grade' => '50ppm', 'status' => DocStatus::Paid, 'days' => 40],
            ['customer' => 1, 'type' => DocType::Invoice, 'litres' => '7200', 'price' => '23.7000', 'grade' => '50ppm', 'status' => DocStatus::Sent, 'days' => 45],
        ];

        foreach ($documents as $spec) {
            $customer = $customers[$spec['customer']];
            $issuedOn = today()->subDays($spec['days']);

            $document = $writer->create([
                'doc_type' => $spec['type'],
                'customer_id' => $customer->id,
                'company' => $customer->company,
                'contact_name' => $customer->contact_name,
                'phone' => $customer->phone,
                'email' => $customer->email,
                'delivery_address' => $customer->delivery_address,
                'issued_on' => $issuedOn->toDateString(),
                'fulfilment' => 'delivery',
                'vat_mode' => 'inclusive',
                'lines' => [[
                    'grade_id' => $spec['grade'],
                    'code' => $spec['grade'] === '50ppm' ? 'D50-BULK' : 'D500-BULK',
                    'description' => $spec['grade'] === '50ppm' ? 'Diesel 50ppm' : 'Diesel 500ppm',
                    'litres' => $spec['litres'],
                    'price_per_litre' => $spec['price'],
                ]],
            ], $owner);

            $document->update([
                'status' => $spec['status'],
                'public_token' => $spec['status'] === DocStatus::Draft ? null : $writer->newPublicToken(),
                'paid_at' => $spec['status'] === DocStatus::Paid ? $issuedOn->addDays(7) : null,
            ]);
        }

        foreach ([['ND 44 221', 'A1'], ['CA 887 190', 'A2'], ['GP 12 KLM GP', 'B4']] as [$reg, $bay]) {
            YardBooking::query()->create([
                'customer_id' => $customers->random()->id,
                'vehicle_reg' => $reg,
                'bay' => $bay,
                'check_in' => now()->subDays(random_int(1, 4)),
                'rate_per_day' => '180.00',
            ]);
        }

        $this->command->info('Demo data seeded.');
    }
}
