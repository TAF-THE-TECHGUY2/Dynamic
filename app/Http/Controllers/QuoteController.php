<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\DocType;
use App\Enums\VatMode;
use App\Mail\PublicEstimateForBusiness;
use App\Mail\PublicEstimateForCustomer;
use App\Models\Grade;
use App\Models\User;
use App\Services\DocumentWriter;
use App\Services\PriceBook;
use App\Services\Settings;
use App\Support\Money;
use App\Support\Totals;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

/**
 * The public quote calculator.
 *
 * Labelled an estimate throughout, because it is one. It is not a quote until
 * the owner issues one from the admin console.
 */
final class QuoteController extends Controller
{
    public function __construct(
        private readonly PriceBook $prices,
        private readonly Settings $settings,
        private readonly DocumentWriter $writer,
    ) {}

    public function show(): View
    {
        $display = $this->prices->display();

        return view('pages.quote', [
            'prices' => $display['grades'],
            'lastChangedAt' => $display['last_changed_at'] === null
                ? null
                : Carbon::parse($display['last_changed_at']),
            'minOrderLitres' => $this->settings->minOrderLitres(),
            'vatRegistered' => $this->settings->isVatRegistered(),
        ]);
    }

    /**
     * Price an estimate.
     *
     * The page debounces a POST here rather than porting the totals arithmetic
     * into Alpine, so there is no third implementation of the money maths to
     * keep in step with the PHP and the TypeScript.
     */
    public function calculate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'grade_id' => ['required', 'string', Rule::exists('grades', 'id')->where('active', true)],
            'litres' => ['required', 'numeric', 'min:0', 'max:9999999'],
        ]);

        $price = $this->prices->priceFor($validated['grade_id']);

        if ($price === null) {
            return response()->json([
                'available' => false,
                'message' => "Today's price for that grade has not been published yet.",
            ]);
        }

        $vatMode = $this->settings->isVatRegistered() ? VatMode::Inclusive : VatMode::None;

        $totals = Totals::compute(
            [['litres' => (string) $validated['litres'], 'price_per_litre' => $price]],
            $vatMode,
            $this->settings->vatRate(),
        );

        $minimum = $this->settings->minOrderLitres();

        return response()->json([
            'available' => true,
            'price_per_litre' => $price,
            'price_per_litre_display' => Money::format((int) Money::toCents($price)),
            'litres_display' => Money::litres((string) $validated['litres']),
            'subtotal_display' => Money::format($totals->subtotalCents),
            'vat_display' => Money::format($totals->vatCents),
            'total_display' => Money::format($totals->totalCents),
            'shows_vat' => $vatMode->showsVatRows(),
            'below_minimum' => (float) $validated['litres'] < $minimum,
            'minimum_litres' => $minimum,
        ]);
    }

    /**
     * "Email me this estimate."
     *
     * Creates a draft document with a public token so the owner sees it waiting
     * in /admin/documents, then queues mail to the customer and the business.
     * It stays a draft: nothing has been issued yet.
     */
    public function submit(Request $request): RedirectResponse
    {
        $minimum = $this->settings->minOrderLitres();

        $validated = $request->validate([
            'grade_id' => ['required', 'string', Rule::exists('grades', 'id')->where('active', true)],
            'litres' => ['required', 'numeric', "min:{$minimum}", 'max:9999999'],
            'fulfilment' => ['required', Rule::in(['delivery', 'collection'])],
            'suburb' => ['nullable', 'string', 'max:120'],
            'name' => ['required', 'string', 'max:120'],
            'company' => ['nullable', 'string', 'max:160'],
            'phone' => ['required', 'string', 'max:40'],
            'email' => ['required', 'email', 'max:160'],
        ], [
            'litres.min' => "The minimum order for delivery is {$minimum} litres. Call us for smaller volumes.",
        ]);

        $grade = Grade::query()->whereKey($validated['grade_id'])->firstOrFail();
        $price = $this->prices->priceFor($grade->id);

        if ($price === null) {
            return back()
                ->withInput()
                ->withErrors(['grade_id' => "Today's price for that grade has not been published yet. Please call us."]);
        }

        $document = $this->writer->create([
            'doc_type' => DocType::Quote,
            // A `nullable` field the visitor left out is absent from validated()
            // entirely, not present-and-null.
            'company' => ($validated['company'] ?? '') ?: $validated['name'],
            'contact_name' => $validated['name'],
            'phone' => $validated['phone'],
            'email' => $validated['email'],
            'delivery_address' => $validated['suburb'] ?? null,
            'fulfilment' => $validated['fulfilment'],
            'vat_mode' => VatMode::Inclusive->value,
            'notes' => 'Estimate requested from the website. Not yet issued.',
            'public_token' => $this->writer->newPublicToken(),
            'lines' => [[
                'grade_id' => $grade->id,
                'code' => $grade->code,
                'description' => $grade->label,
                'litres' => (string) $validated['litres'],
                'price_per_litre' => $price,
            ]],
        ], $this->estimateAuthor());

        Mail::to($validated['email'])->queue(new PublicEstimateForCustomer($document));

        $businessEmail = $this->settings->string('business.email');

        if ($businessEmail !== '' && ! str_starts_with($businessEmail, 'TODO')) {
            Mail::to($businessEmail)->queue(new PublicEstimateForBusiness($document));
        }

        return back()->with('quote.submitted', $document->publicUrl());
    }

    /**
     * Website estimates are attributed to the owner account, because
     * documents.created_by is not nullable and a public visitor has no user.
     */
    private function estimateAuthor(): User
    {
        return User::query()->where('role', 'owner')->orderBy('created_at')->firstOrFail();
    }
}
