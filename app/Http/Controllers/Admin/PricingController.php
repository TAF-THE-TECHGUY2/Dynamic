<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Grade;
use App\Models\PriceHistory;
use App\Services\PriceBook;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class PricingController extends Controller
{
    public function __construct(private readonly PriceBook $prices) {}

    public function index(Request $request): Response
    {
        $this->authorize('view-prices');

        return Inertia::render('admin/pricing/index', [
            'grades' => $this->gradesWithPrices(),
            'lastChangedAt' => $this->prices->lastChangedAt()?->toIso8601String(),
            'history' => $this->prices->history()->map(fn (PriceHistory $row) => [
                'id' => $row->id,
                'grade' => $row->grade->label,
                'price_per_litre' => $row->price_per_litre,
                'vat_inclusive' => $row->vat_inclusive,
                'effective_from' => $row->effective_from->toIso8601String(),
                'published_by' => $row->publisher?->name,
            ]),
            'canPublish' => $request->user()->can('publish-prices'),
        ]);
    }

    /**
     * Publish today's price.
     *
     * Appends a row per changed grade — it never updates one in place, so the
     * history stays auditable. PriceBook::publish() flushes the page cache, so
     * the homepage shows the new figure on the next request rather than up to
     * sixty seconds later.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('publish-prices');

        $validated = $request->validate([
            'prices' => ['required', 'array', 'min:1'],
            'prices.*' => ['required', 'numeric', 'min:0', 'max:999999.9999'],
        ]);

        $known = Grade::query()->active()->pluck('id')->all();

        $prices = array_intersect_key(
            array_map(strval(...), $validated['prices']),
            array_flip($known),
        );

        $published = $this->prices->publish($prices, $request->user());

        return back()->with('success', $published->isEmpty()
            ? 'Nothing changed — those are already today\'s prices.'
            : 'Price published. The website and every new quote are using it now.');
    }

    /** @return array<int, array<string, mixed>> */
    private function gradesWithPrices(): array
    {
        $current = $this->prices->current();

        return Grade::query()->active()->get()->map(function (Grade $grade) use ($current) {
            // Null until the first price for that grade is published.
            $row = $current->get($grade->id);

            return [
                'id' => $grade->id,
                'label' => $grade->label,
                'code' => $grade->code,
                'blurb' => $grade->blurb,
                'price_per_litre' => $row['price_per_litre'] ?? null,
                'effective_from' => $row === null ? null : $row['effective_from']->toIso8601String(),
            ];
        })->all();
    }
}
