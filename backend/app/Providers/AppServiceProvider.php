<?php

namespace App\Providers;

use App\Services\Bulletins\MoyenneCalculator;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Règles du bulletin lues dans config/bulletin.php (surchargeables par .env)
        $this->app->bind(MoyenneCalculator::class, fn () => MoyenneCalculator::depuisConfig());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 5 tentatives de connexion par minute et par couple e-mail / IP
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)
            ->by(mb_strtolower((string) $request->input('email')).'|'.$request->ip()));
    }
}
