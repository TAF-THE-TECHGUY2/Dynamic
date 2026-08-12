<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\YardBooking;
use App\Services\Settings;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class YardController extends Controller
{
    public function __construct(private readonly Settings $settings) {}

    public function index(): Response
    {
        $this->authorize('viewAny', YardBooking::class);

        $bays = $this->settings->int('yard.bays', 24);
        $open = YardBooking::query()->open()->with('customer')->orderBy('check_in')->get();

        return Inertia::render('admin/yard/index', [
            'bays' => $bays,
            'occupied' => $open->count(),
            'free' => max(0, $bays - $open->count()),
            'defaultRate' => $this->settings->get('yard.rate_per_day'),
            'open' => $open->map($this->present(...)),
            'recent' => YardBooking::query()
                ->whereNotNull('check_out')
                ->with('customer')
                ->orderByDesc('check_out')
                ->limit(20)
                ->get()
                ->map($this->present(...)),
            'customers' => Customer::query()->orderBy('company')->get(['id', 'company']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', YardBooking::class);

        $validated = $request->validate([
            'customer_id' => ['nullable', 'uuid', 'exists:customers,id'],
            'company' => ['nullable', 'string', 'max:160'],
            'vehicle_reg' => ['required', 'string', 'max:20'],
            'bay' => ['nullable', 'string', 'max:20'],
            'rate_per_day' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        YardBooking::query()->create([
            ...$validated,
            'rate_per_day' => $validated['rate_per_day'] ?? $this->settings->get('yard.rate_per_day'),
            'check_in' => now(),
        ]);

        return back()->with('success', "{$validated['vehicle_reg']} checked in.");
    }

    public function checkOut(YardBooking $booking): RedirectResponse
    {
        $this->authorize('update', $booking);

        $booking->update(['check_out' => now()]);

        return back()->with('success', sprintf(
            '%s checked out after %d day(s) — %s.',
            $booking->vehicle_reg,
            $booking->daysStayed(),
            Money::format($booking->chargeCents()),
        ));
    }

    /**
     * Walk-ins have no customer record, only a company name typed in at the
     * gate. The foreign key is what says which of the two applies — it is set
     * to null if the customer is ever deleted.
     */
    private function companyFor(YardBooking $booking): string
    {
        if ($booking->customer_id !== null) {
            return $booking->customer->company;
        }

        return $booking->company ?? '—';
    }

    /** @return array<string, mixed> */
    private function present(YardBooking $booking): array
    {
        return [
            'id' => $booking->id,
            'company' => $this->companyFor($booking),
            'vehicle_reg' => $booking->vehicle_reg,
            'bay' => $booking->bay,
            'check_in' => $booking->check_in->toIso8601String(),
            'check_in_display' => $booking->check_in->format('d M H:i'),
            'check_out_display' => $booking->check_out?->format('d M H:i'),
            'days' => $booking->daysStayed(),
            'charge_display' => Money::format($booking->chargeCents()),
            'is_open' => $booking->isOpen(),
        ];
    }
}
