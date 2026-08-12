<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Setting;
use App\Services\Settings;
use Illuminate\Database\Seeder;

/**
 * Seeds the settings table from config/business.php.
 *
 * Only fills in keys that are missing, so re-running the seeder never
 * overwrites something the owner has since corrected in the admin console.
 */
class SettingSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->defaults() as $key => $value) {
            Setting::query()->firstOrCreate(['key' => $key], ['value' => $value]);
        }

        app(Settings::class)->flush();
    }

    /** @return array<string, mixed> */
    private function defaults(): array
    {
        return [
            'business.trading_name' => config('business.trading_name'),
            'business.registered_name' => config('business.registered_name'),
            'business.reg_number' => config('business.reg_number'),
            'business.vat_number' => config('business.vat_number'),
            'business.email' => config('business.email'),
            'business.phone' => config('business.phone'),
            'business.whatsapp' => config('business.whatsapp'),
            'business.address_line1' => config('business.address.line1'),
            'business.address_line2' => config('business.address.line2'),
            'business.address_line3' => config('business.address.line3'),
            'business.hours_weekday' => config('business.hours.weekday'),
            'business.hours_saturday' => config('business.hours.saturday'),
            'business.hours_note' => config('business.hours.note'),

            'banking.bank' => config('business.banking.bank'),
            'banking.account_name' => config('business.banking.account_name'),
            'banking.account_number' => config('business.banking.account_number'),
            'banking.branch_code' => config('business.banking.branch_code'),
            'banking.account_type' => config('business.banking.account_type'),
            'banking.reference_note' => 'Please use the document number as your payment reference.',

            'vat_rate' => '0.15',

            // South African diesel reprices monthly. A quote that outlives a
            // price change is a direct margin loss, so the default window is short.
            'quote_valid_hours' => 48,
            'invoice_due_days' => 30,

            'min_order_litres' => 500,
            'delivery_note' => 'Free delivery around Gauteng. Outlying areas quoted on distance.',

            'yard.bays' => config('business.yard.bays'),
            'yard.rate_per_day' => null,

            'terms.quote' => implode("\n", [
                'Prices are subject to change without notice and reflect the rate at the date of issue.',
                'This quote is valid until the date shown above.',
                'Fuel remains the property of Dynamic Solutions until paid for in full.',
                'Delivery is free around Gauteng; outlying areas are quoted on distance.',
            ]),

            'terms.proforma' => implode("\n", [
                'This pro forma is not a tax invoice.',
                'Prices reflect the rate at the date of issue and are subject to change.',
                'Goods are supplied once payment reflects, or on agreed COD terms.',
            ]),

            'terms.invoice' => implode("\n", [
                'Payment is due by the date shown above.',
                'Please use the document number as your payment reference.',
                'Fuel remains the property of Dynamic Solutions until paid for in full.',
            ]),
        ];
    }
}
