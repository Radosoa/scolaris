<?php

use App\Services\Finances\RelancesService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Avertissements de retard d'écolage : parents, direction et secrétariat (chaque matin)
Artisan::command('scolaris:relancer-impayes', function (RelancesService $relances) {
    ['familles' => $familles] = $relances->relancer();
    $this->info($familles ? "{$familles} famille(s) avertie(s)." : 'Aucun nouveau retard.');
})->purpose("Avertit les familles en retard d'écolage et la direction");

Schedule::command('scolaris:relancer-impayes')->dailyAt('07:00');
