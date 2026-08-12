<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Mail\ContactMessage;
use App\Services\PriceBook;
use App\Services\Settings;
use App\Support\PageCache;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * The public marketing pages.
 *
 * Plain server-rendered Blade. These have to rank on Google and load instantly
 * for someone standing next to a truck on mobile data, so there is no
 * hydration cost here at all — the only JavaScript is Alpine, for the pump
 * counter and the mobile menu.
 */
final class PageController extends Controller
{
    public function __construct(
        private readonly PriceBook $prices,
        private readonly Settings $settings,
    ) {}

    public function home(): View
    {
        return view('pages.home', $this->priceData());
    }

    public function bulkDiesel(): View
    {
        return view('pages.bulk-diesel', [
            ...$this->priceData(),
            'minOrderLitres' => $this->settings->minOrderLitres(),
        ]);
    }

    public function truckStop(): View
    {
        return view('pages.truck-stop', [
            'bays' => $this->settings->int('yard.bays', 24),
            'ratePerDay' => $this->settings->get('yard.rate_per_day'),
        ]);
    }

    public function contact(): View
    {
        return view('pages.contact');
    }

    public function sendContactMessage(Request $request): RedirectResponse
    {
        $fields = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'company' => ['nullable', 'string', 'max:160'],
            'phone' => ['required', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:160'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $businessEmail = $this->settings->string('business.email');

        if ($businessEmail !== '' && ! str_starts_with($businessEmail, 'TODO')) {
            Mail::to($businessEmail)->queue(new ContactMessage($fields));
        } else {
            // Better a log line the owner can be pointed at than a message that
            // silently evaporates because BUSINESS_EMAIL was never filled in.
            Log::warning('Contact form submitted but no business email is configured.', $fields);
        }

        return back()->with('contact.sent', true);
    }

    /**
     * Today's price for every active grade, cached for sixty seconds.
     *
     * PageCache::bump() runs when a price is published or a setting changes, so
     * the homepage never shows a price the owner has already replaced.
     *
     * @return array{prices: array<int, array<string, mixed>>, lastChangedAt: Carbon|null}
     */
    private function priceData(): array
    {
        $display = PageCache::remember('prices', fn () => $this->prices->display());

        return [
            'prices' => $display['grades'],
            'lastChangedAt' => $display['last_changed_at'] === null
                ? null
                : Carbon::parse($display['last_changed_at']),
        ];
    }
}
