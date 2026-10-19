<?php

namespace App\Providers;

use App\Models\Ecole;
use App\Services\Bulletins\MoyenneCalculator;
use App\Support\EcoleCourante;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
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

        $this->app->singleton(EcoleCourante::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 5 tentatives de connexion par minute et par couple école / e-mail / IP
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)
            ->by($request->header(config('ecoles.entete')).'|'.mb_strtolower((string) $request->input('email')).'|'.$request->ip()));

        // Une tâche de fond (PDF, notifications) s'exécute dans l'école qui l'a lancée
        Queue::createPayloadUsing(fn () => ['ecole' => app(EcoleCourante::class)->get()?->code]);
        Event::listen(JobProcessing::class, function (JobProcessing $event) {
            $code = $event->job->payload()['ecole'] ?? null;
            if ($code && ($ecole = Ecole::where('code', $code)->first())) {
                app(EcoleCourante::class)->activer($ecole);
            }
        });
    }
}
