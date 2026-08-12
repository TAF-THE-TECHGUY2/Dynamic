<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Business facts
|--------------------------------------------------------------------------
|
| These seed the `settings` table on first run. After that the table is the
| source of truth and the owner edits it at /admin/settings — correcting a
| bank account should not need a deploy.
|
| Anything reading "TODO" here is printed on quotes and invoices in front of
| customers. Fill it in before going live.
|
*/

return [

    'trading_name' => env('BUSINESS_TRADING_NAME', 'Dynamic Solutions'),
    'registered_name' => env('BUSINESS_REGISTERED_NAME', 'TODO — REGISTERED NAME (PTY) LTD'),
    'reg_number' => env('BUSINESS_REG_NUMBER', 'TODO — 20XX/XXXXXX/07'),

    // Blank means not VAT registered, which forces every document to VAT mode
    // `none` and hides VAT rows entirely. Showing a VAT line without a VAT
    // number is not compliant with SARS requirements.
    'vat_number' => env('BUSINESS_VAT_NUMBER', ''),

    'email' => env('BUSINESS_EMAIL', 'TODO@example.com'),
    'phone' => env('BUSINESS_PHONE', '061 687 4334'),
    'whatsapp' => env('BUSINESS_WHATSAPP', '27616874334'),

    'address' => [
        'line1' => env('BUSINESS_ADDRESS_LINE1', '31 Dassie Road'),
        'line2' => env('BUSINESS_ADDRESS_LINE2', 'Zesfontein, Benoni'),
        'line3' => env('BUSINESS_ADDRESS_LINE3', 'Gauteng, South Africa'),
    ],

    'hours' => [
        'weekday' => env('BUSINESS_HOURS_WEEKDAY', 'TODO — Mon–Fri hours'),
        'saturday' => env('BUSINESS_HOURS_SATURDAY', 'TODO — Saturday hours'),
        'note' => env('BUSINESS_HOURS_NOTE', 'After-hours by arrangement'),
    ],

    'banking' => [
        'bank' => env('BUSINESS_BANK_NAME', 'TODO — bank'),
        'account_name' => env('BUSINESS_BANK_ACCOUNT_NAME', 'TODO — account name'),
        'account_number' => env('BUSINESS_BANK_ACCOUNT_NUMBER', 'TODO — account number'),
        'branch_code' => env('BUSINESS_BANK_BRANCH_CODE', 'TODO — branch code'),
        'account_type' => env('BUSINESS_BANK_ACCOUNT_TYPE', 'TODO — account type'),
    ],

    'yard' => [
        // Zero is the explicit "not supplied yet" value. The Settings screen
        // counts it in the go-live warning until the owner enters capacity.
        'bays' => (int) env('BUSINESS_YARD_BAYS', 0),
    ],

    // Fixed brand assets. Used where the mockup uses them and nowhere else.
    'taglines' => [
        'primary' => 'Fueling your business. Powering your journey.',
        'service' => 'Trusted fuel. Driven by service.',
        'quality' => 'Quality fuel. On time. Every time.',
    ],

    'owner' => [
        'name' => env('OWNER_NAME', 'Tafara Siziba'),
        'email' => env('OWNER_EMAIL'),
        'password' => env('OWNER_PASSWORD'),
    ],

];
