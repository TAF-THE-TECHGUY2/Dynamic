<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Schema.org JSON-LD for the yard at 31 Dassie Road.
 *
 * A diesel supplier is found by people searching "diesel near me" from a cab,
 * so the LocalBusiness block carries the address, hours and phone number that
 * put the yard on a map result.
 */
final class StructuredData
{
    /** @return array<string, mixed> */
    public static function localBusiness(): array
    {
        $settings = settings();

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'GasStation',
            'name' => $settings->string('business.trading_name', 'Dynamic Solutions'),
            'legalName' => self::withoutTodo($settings->string('business.registered_name')),
            'description' => 'Bulk diesel supply (50ppm and 500ppm), pump sales and secure truck parking in Benoni, Gauteng.',
            'url' => config('app.url'),
            'telephone' => $settings->string('business.phone'),
            'email' => self::withoutTodo($settings->string('business.email')),
            'vatID' => self::withoutTodo($settings->string('business.vat_number')),
            'areaServed' => [
                '@type' => 'AdministrativeArea',
                'name' => 'Gauteng, South Africa',
            ],
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => $settings->string('business.address_line1', '31 Dassie Road'),
                'addressLocality' => 'Benoni',
                'addressRegion' => 'Gauteng',
                'addressCountry' => 'ZA',
            ],
            'openingHours' => self::openingHours(),
            'currenciesAccepted' => 'ZAR',
            'paymentAccepted' => 'Cash, EFT, COD',
        ], fn ($value) => $value !== null && $value !== '' && $value !== []);
    }

    /** @return array<int, string> */
    private static function openingHours(): array
    {
        $settings = settings();

        return array_values(array_filter([
            self::withoutTodo($settings->string('business.hours_weekday')),
            self::withoutTodo($settings->string('business.hours_saturday')),
        ], fn (?string $value) => $value !== null));
    }

    /**
     * Placeholders belong in the admin console, not in structured data a search
     * engine will index.
     */
    private static function withoutTodo(string $value): ?string
    {
        return str_starts_with(trim($value), 'TODO') || trim($value) === '' ? null : $value;
    }
}
