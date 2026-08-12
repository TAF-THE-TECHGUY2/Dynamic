<?php

declare(strict_types=1);

use App\Services\Settings;
use App\Support\Money;

if (! function_exists('settings')) {
    /**
     * Read a business setting: settings('business.phone').
     *
     * Call with no arguments for the repository itself.
     */
    function settings(?string $key = null, mixed $default = null): mixed
    {
        $settings = app(Settings::class);

        return $key === null ? $settings : $settings->get($key, $default);
    }
}

if (! function_exists('whatsapp_link')) {
    /**
     * A wa.me link to the business, with the message pre-filled.
     */
    function whatsapp_link(string $message = ''): string
    {
        $number = preg_replace('/\D+/', '', (string) settings('business.whatsapp', ''));

        return 'https://wa.me/'.$number.($message === '' ? '' : '?text='.rawurlencode($message));
    }
}

if (! function_exists('money')) {
    /**
     * Format an integer number of cents as rand: money(8225000) === 'R82,250.00'.
     */
    function money(int $cents, bool $symbol = true): string
    {
        return Money::format($cents, $symbol);
    }
}

if (! function_exists('litres')) {
    /**
     * Format a litres decimal string for display: litres('12000.00') === '12,000'.
     */
    function litres(string $value): string
    {
        return Money::litres($value);
    }
}
