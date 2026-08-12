<?php

namespace App\Providers;

use App\Policies\PricePolicy;
use App\Policies\SettingPolicy;
use App\Services\PriceBook;
use App\Services\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Settings and the price book are read many times per request and
        // memoise their own cache lookups, so they are worth resolving once.
        $this->app->singleton(Settings::class);
        $this->app->singleton(PriceBook::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureGates();
    }

    /**
     * Pricing and settings are guarded by abilities rather than a model policy,
     * because there is no model instance to hang them off.
     */
    protected function configureGates(): void
    {
        Gate::define('publish-prices', [PricePolicy::class, 'publish']);
        Gate::define('view-prices', [PricePolicy::class, 'view']);
        Gate::define('view-settings', [SettingPolicy::class, 'view']);
        Gate::define('update-settings', [SettingPolicy::class, 'update']);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
