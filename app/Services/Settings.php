<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use App\Support\PageCache;
use Illuminate\Support\Facades\Cache;

/**
 * The settings table, read through a cache.
 *
 * Business facts printed on documents live here rather than in config, so the
 * owner can correct a bank account without a deploy. config/business.php only
 * supplies the values this table is seeded with on first run.
 */
final class Settings
{
    private const CACHE_KEY = 'settings.all';

    /** @var array<string, mixed>|null */
    private ?array $loaded = null;

    public function get(string $key, mixed $default = null): mixed
    {
        // Setting keys contain dots as part of the key itself ('banking.bank'
        // is one flat row, not a nested structure), so this is an exact lookup.
        // data_get would explode it into segments and never find anything.
        return $this->all()[$key] ?? $default;
    }

    public function string(string $key, string $default = ''): string
    {
        $value = $this->get($key, $default);

        return is_scalar($value) ? (string) $value : $default;
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->get($key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->loaded ??= Cache::rememberForever(
            self::CACHE_KEY,
            fn () => Setting::query()->pluck('value', 'key')->all(),
        );
    }

    public function set(string $key, mixed $value): void
    {
        Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);

        $this->flush();
    }

    /** @param  array<string, mixed>  $values */
    public function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }

        $this->flush();
    }

    public function flush(): void
    {
        $this->loaded = null;

        Cache::forget(self::CACHE_KEY);

        // Settings appear on the marketing pages (address, hours, phone), so a
        // change has to reach the public site immediately.
        PageCache::bump();
    }

    /**
     * VAT is only ever shown when there is a VAT number to show it against.
     *
     * Printing a VAT line without a VAT number is not compliant with SARS
     * requirements, so this is checked wherever a document is priced.
     */
    public function isVatRegistered(): bool
    {
        return trim($this->string('business.vat_number')) !== '';
    }

    public function vatRate(): string
    {
        return $this->string('vat_rate', '0.15');
    }

    /** How long a new quote stays valid. South African diesel reprices monthly. */
    public function quoteValidHours(): int
    {
        return $this->int('quote_valid_hours', 48);
    }

    public function minOrderLitres(): int
    {
        return $this->int('min_order_litres', 500);
    }

    /**
     * The business block printed at the top right of every document.
     *
     * @return array<string, string>
     */
    public function businessBlock(): array
    {
        return [
            'trading_name' => $this->string('business.trading_name'),
            'registered_name' => $this->string('business.registered_name'),
            'reg_number' => $this->string('business.reg_number'),
            'vat_number' => $this->string('business.vat_number'),
            'email' => $this->string('business.email'),
            'phone' => $this->string('business.phone'),
            'whatsapp' => $this->string('business.whatsapp'),
            'address_line1' => $this->string('business.address_line1'),
            'address_line2' => $this->string('business.address_line2'),
            'address_line3' => $this->string('business.address_line3'),
        ];
    }

    /** The banking text snapshotted onto a document at issue time. */
    public function bankingBlock(): string
    {
        $lines = array_filter([
            $this->string('banking.bank'),
            $this->string('banking.account_name'),
            $this->string('banking.account_number') !== ''
                ? 'Acc no: '.$this->string('banking.account_number')
                : '',
            $this->string('banking.branch_code') !== ''
                ? 'Branch: '.$this->string('banking.branch_code')
                : '',
            $this->string('banking.account_type'),
            $this->string('banking.reference_note'),
        ], fn (string $line) => trim($line) !== '');

        return implode("\n", $lines);
    }

    public function termsFor(string $docType): string
    {
        return $this->string("terms.{$docType}", $this->string('terms.quote'));
    }
}
