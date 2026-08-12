<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Business settings. Owner only — these carry the banking details printed on
 * every invoice.
 */
final class SettingsController extends Controller
{
    /**
     * The keys the owner may edit, and how each is validated.
     *
     * Everything else in the settings table is either derived or seeded.
     */
    private const EDITABLE = [
        'business.trading_name' => ['required', 'string', 'max:160'],
        'business.registered_name' => ['required', 'string', 'max:160'],
        'business.reg_number' => ['nullable', 'string', 'max:40'],
        'business.vat_number' => ['nullable', 'string', 'max:40'],
        'business.email' => ['nullable', 'email', 'max:160'],
        'business.phone' => ['required', 'string', 'max:40'],
        'business.whatsapp' => ['required', 'string', 'max:20'],
        'business.address_line1' => ['required', 'string', 'max:160'],
        'business.address_line2' => ['nullable', 'string', 'max:160'],
        'business.address_line3' => ['nullable', 'string', 'max:160'],
        'business.hours_weekday' => ['nullable', 'string', 'max:120'],
        'business.hours_saturday' => ['nullable', 'string', 'max:120'],
        'business.hours_note' => ['nullable', 'string', 'max:120'],
        'banking.bank' => ['nullable', 'string', 'max:80'],
        'banking.account_name' => ['nullable', 'string', 'max:120'],
        'banking.account_number' => ['nullable', 'string', 'max:40'],
        'banking.branch_code' => ['nullable', 'string', 'max:20'],
        'banking.account_type' => ['nullable', 'string', 'max:40'],
        'banking.reference_note' => ['nullable', 'string', 'max:200'],
        'vat_rate' => ['required', 'numeric', 'min:0', 'max:1'],
        'quote_valid_hours' => ['required', 'integer', 'min:1', 'max:8760'],
        'invoice_due_days' => ['required', 'integer', 'min:0', 'max:365'],
        'min_order_litres' => ['required', 'integer', 'min:0', 'max:1000000'],
        'delivery_note' => ['nullable', 'string', 'max:300'],
        'yard.bays' => ['required', 'integer', 'min:0', 'max:1000'],
        'yard.rate_per_day' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
        'terms.quote' => ['required', 'string', 'max:4000'],
        'terms.proforma' => ['required', 'string', 'max:4000'],
        'terms.invoice' => ['required', 'string', 'max:4000'],
    ];

    public function __construct(private readonly Settings $settings) {}

    public function index(): Response
    {
        $this->authorize('view-settings');

        return Inertia::render('admin/settings/index', [
            'values' => collect(array_keys(self::EDITABLE))
                ->mapWithKeys(fn (string $key) => [$key => $this->settings->get($key)])
                ->all(),
            'vatRegistered' => $this->settings->isVatRegistered(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorize('update-settings');

        // Dots are part of the key, not a path, so the rules are keyed with an
        // escape to stop the validator treating them as nested attributes.
        $rules = collect(self::EDITABLE)
            ->mapWithKeys(fn (array $rule, string $key) => [str_replace('.', '\.', $key) => $rule])
            ->all();

        $request->validate($rules);

        $values = collect(array_keys(self::EDITABLE))
            ->filter(fn (string $key) => $request->has($key))
            ->mapWithKeys(fn (string $key) => [$key => $request->input($key)])
            ->all();

        $this->settings->setMany($values);

        return back()->with('success', 'Settings saved. Documents already issued are unchanged.');
    }
}
