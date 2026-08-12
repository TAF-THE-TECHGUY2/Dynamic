<?php

namespace App\Http\Middleware;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
                'role' => $user?->role,

                // Gates the sidebar in React. The controllers check the same
                // policies again — a hidden nav item is not authorisation.
                'can' => [
                    'viewDocuments' => $user !== null && Gate::forUser($user)->allows('viewAny', Document::class),
                    'viewCustomers' => $user !== null && $user->role->seesSales(),
                    'viewPrices' => $user !== null && Gate::forUser($user)->allows('view-prices'),
                    'publishPrices' => $user !== null && Gate::forUser($user)->allows('publish-prices'),
                    'viewSettings' => $user !== null && Gate::forUser($user)->allows('view-settings'),
                    'viewYard' => $user !== null,
                ],
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
