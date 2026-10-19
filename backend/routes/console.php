<?php

use App\Models\Ecole;
use App\Services\Ecoles\GestionEcoles;
use App\Services\Finances\RelancesService;
use App\Support\EcoleCourante;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Avertissements de retard d'écolage : parents, direction et secrétariat (chaque matin, dans chaque école)
Artisan::command('scolaris:relancer-impayes', function (RelancesService $relances, EcoleCourante $ecoles) {
    $relancer = function (?Ecole $ecole) use ($relances) {
        ['familles' => $familles] = $relances->relancer();
        $prefixe = $ecole ? "[{$ecole->code}] " : '';
        $this->info($prefixe.($familles ? "{$familles} famille(s) avertie(s)." : 'Aucun nouveau retard.'));
    };

    config('ecoles.multi') ? $ecoles->pourChaque($relancer) : $relancer(null);
})->purpose("Avertit les familles en retard d'écolage et la direction");

Schedule::command('scolaris:relancer-impayes')->dailyAt('07:00');

// --- Multi-établissement : exploitation ---------------------------------------

Artisan::command('ecole:creer {code : identifiant court, ex. lycee-andohalo} {nom : nom affiché de l’école}
    {--email= : e-mail du compte de la direction} {--mot-de-passe= : mot de passe de ce compte}
    {--demo : remplir la base avec les données de démonstration}', function (GestionEcoles $gestion) {
    try {
        $ecole = $gestion->creer($this->argument('code'), $this->argument('nom'), $this->option('email'), $this->option('mot-de-passe'), (bool) $this->option('demo'));
    } catch (InvalidArgumentException $e) {
        $this->error($e->getMessage());

        return 1;
    }
    $this->info("École « {$ecole->nom} » créée (base {$ecole->base_de_donnees}). Code de connexion : {$ecole->code}");
})->purpose('Crée une école et sa base de données');

Artisan::command('ecole:importer {code} {nom} {--base= : base existante (par défaut DB_DATABASE)}', function (GestionEcoles $gestion) {
    $ecole = $gestion->importer($this->argument('code'), $this->argument('nom'), $this->option('base') ?: (string) config('database.connections.mysql.database'));
    $this->info("Base {$ecole->base_de_donnees} rattachée à l’école « {$ecole->code} ».");
})->purpose('Rattache une base existante (installation mono-école) comme école');

Artisan::command('ecole:lister', function () {
    $this->table(['Code', 'Nom', 'Base', 'Active'], Ecole::orderBy('code')->get()->map(fn (Ecole $e) => [$e->code, $e->nom, $e->base_de_donnees, $e->actif ? 'oui' : 'non']));
})->purpose('Liste les écoles');

Artisan::command('ecole:migrer', function (GestionEcoles $gestion) {
    $gestion->migrerToutes(fn (string $ligne) => $this->info($ligne));
})->purpose('Met à jour la base centrale et la base de chaque école');

Artisan::command('ecole:suspendre {code} {--reactiver}', function () {
    $ecole = Ecole::where('code', $this->argument('code'))->firstOrFail();
    $ecole->update(['actif' => (bool) $this->option('reactiver')]);
    $this->info($ecole->actif ? "« {$ecole->code} » réactivée." : "« {$ecole->code} » suspendue : plus aucune connexion possible.");
})->purpose('Suspend (ou réactive avec --reactiver) l’accès à une école');
