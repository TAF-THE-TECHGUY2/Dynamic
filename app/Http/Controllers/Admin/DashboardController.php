<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Grade;
use App\Models\YardBooking;
use App\Services\PriceBook;
use App\Services\Settings;
use App\Support\DocumentPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController extends Controller
{
    public function __construct(
        private readonly PriceBook $prices,
        private readonly Settings $settings,
    ) {}

    public function __invoke(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        // The yard attendant has no business on a page made of revenue figures.
        if ($user->isAttendant()) {
            return redirect()->route('admin.yard.index');
        }

        $current = $this->prices->current();
        $bays = $this->settings->int('yard.bays', 24);
        $occupied = YardBooking::query()->open()->count();

        return Inertia::render('admin/dashboard', [
            'greeting' => $this->greeting().', '.strtoupper($user->firstName()),
            'metrics' => DocumentPresenter::dashboardMetrics(),
            'recent' => Document::query()
                ->with('lines')
                ->orderByDesc('created_at')
                ->limit(6)
                ->get()
                ->map(DocumentPresenter::forList(...)),
            'pricing' => [
                'grades' => Grade::query()->active()->get()->map(fn (Grade $grade) => [
                    'id' => $grade->id,
                    'label' => $grade->label,
                    'blurb' => $grade->blurb,
                    'price_per_litre' => $current->get($grade->id)['price_per_litre'] ?? null,
                ])->values(),
                'lastChangedAt' => $this->prices->lastChangedAt()?->toIso8601String(),
                'canPublish' => $user->can('publish-prices'),
            ],
            'yard' => [
                'bays' => $bays,
                'occupied' => $occupied,
                'free' => max(0, $bays - $occupied),
                'checkingOutToday' => YardBooking::query()
                    ->whereDate('check_out', today())
                    ->count(),
            ],
        ]);
    }

    private function greeting(): string
    {
        return match (true) {
            now()->hour < 12 => 'GOOD MORNING',
            now()->hour < 17 => 'GOOD AFTERNOON',
            default => 'GOOD EVENING',
        };
    }
}
