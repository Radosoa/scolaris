<?php

namespace App\Services\Ecoles;

use App\Enums\Role;
use App\Models\Ecole;
use App\Models\User;
use App\Services\ParametresService;
use App\Support\EcoleCourante;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;

/**
 * Exploitation du multi-établissement (commandes ecole:*) : création d'une école avec
 * sa base, rattachement d'une base existante, migrations de toutes les bases.
 */
class GestionEcoles
{
    public function __construct(
        private readonly EcoleCourante $courante,
        private readonly ParametresService $parametres,
    ) {}

    /**
     * Crée la base de l'école, ses tables, les rôles, et soit le compte de la direction,
     * soit le jeu de données de démonstration complet.
     */
    public function creer(string $code, string $nom, ?string $emailDirection, ?string $motDePasse, bool $demo = false): Ecole
    {
        $this->verifierCode($code);
        if (Ecole::where('code', $code)->exists()) {
            throw new InvalidArgumentException("Le code « {$code} » est déjà utilisé.");
        }
        if (! $demo && (! $emailDirection || ! $motDePasse)) {
            throw new InvalidArgumentException('Indiquez l’e-mail et le mot de passe du compte de la direction (ou --demo).');
        }

        $base = $this->prochaineBase();
        DB::connection('central')->statement("CREATE DATABASE IF NOT EXISTS `{$base}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $ecole = Ecole::create(['code' => $code, 'base_de_donnees' => $base, 'nom' => $nom, 'actif' => true]);

        $this->courante->activer($ecole);
        $this->migrer();
        Artisan::call('db:seed', ['--class' => $demo ? DatabaseSeeder::class : RolePermissionSeeder::class, '--force' => true]);

        if (! $demo) {
            User::create(['name' => 'Direction', 'email' => $emailDirection, 'password' => $motDePasse, 'actif' => true])
                ->assignRole(Role::Admin->value);
        }
        $this->parametres->definirIdentite(['nom' => $nom]);
        // Création terminée : une école sans cette date a été interrompue en cours de remplissage
        $ecole->update(['prete_le' => now()]);

        return $ecole;
    }

    /**
     * Rattache une base existante (installation mono-école d'avant le multi-établissement)
     * et range ses fichiers dans le dossier de l'école.
     */
    public function importer(string $code, string $nom, string $base): Ecole
    {
        $this->verifierCode($code);
        $ecole = Ecole::updateOrCreate(['code' => $code], ['base_de_donnees' => $base, 'nom' => $nom, 'actif' => true, 'prete_le' => now()]);

        $racine = storage_path('app/private');
        $dossier = "{$racine}/ecoles/{$code}";
        File::ensureDirectoryExists($dossier);
        foreach (File::directories($racine) as $sousDossier) {
            if (basename($sousDossier) !== 'ecoles') {
                File::moveDirectory($sousDossier, $dossier.'/'.basename($sousDossier), overwrite: true);
            }
        }

        // L'identité venait des variables ECOLE_* : elle est reprise dans les paramètres de l'école
        $identite = config('ecole');
        $this->courante->activer($ecole);
        $this->parametres->definirIdentite(['nom' => $nom] + $identite);

        return $ecole;
    }

    /** Supprime l'école, sa base et ses fichiers (école de démonstration à recréer). */
    public function supprimer(Ecole $ecole): void
    {
        DB::purge('mysql');
        DB::connection('central')->statement("DROP DATABASE IF EXISTS `{$ecole->base_de_donnees}`");
        File::deleteDirectory(storage_path('app/private/ecoles/'.$ecole->code));
        $ecole->delete();
    }

    /** Applique les migrations à la base de l'école courante. */
    public function migrer(): void
    {
        Artisan::call('migrate', ['--database' => 'mysql', '--force' => true]);
    }

    /** Base centrale puis base de chaque école (après une mise à jour de l'application). */
    public function migrerToutes(callable $journal): void
    {
        Artisan::call('migrate', ['--database' => 'central', '--path' => 'database/migrations/central', '--force' => true]);
        $journal('Base centrale à jour.');

        foreach (Ecole::orderBy('id')->get() as $ecole) {
            $this->courante->activer($ecole);
            $this->migrer();
            $journal("{$ecole->code} ({$ecole->base_de_donnees}) à jour.");
        }
    }

    /** Bases numérotées dans l'ordre de création : scolaris500001, puis scolaris500002… */
    private function prochaineBase(): string
    {
        $prefixe = (string) config('ecoles.prefixe_base');
        $dernier = Ecole::pluck('base_de_donnees')
            ->map(fn (string $base) => str_starts_with($base, $prefixe) && ctype_digit($n = substr($base, strlen($prefixe))) ? (int) $n : 0)
            ->max() ?? 0;

        return $prefixe.max($dernier + 1, (int) config('ecoles.premier_numero'));
    }

    private function verifierCode(string $code): void
    {
        if (! preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $code) || strlen($code) < 2 || strlen($code) > 40) {
            throw new InvalidArgumentException('Code invalide : lettres minuscules, chiffres et tirets (ex. lycee-andohalo).');
        }
    }
}
