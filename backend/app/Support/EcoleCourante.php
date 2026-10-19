<?php

namespace App\Support;

use App\Models\Ecole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;

/**
 * École dont on traite les données (requête HTTP, tâche de fond, commande planifiée).
 * L'activer bascule tout ce qui dépend de l'école : connexion « mysql » vers sa base,
 * dossier de fichiers, cache et permissions en cache.
 */
class EcoleCourante
{
    private ?Ecole $ecole = null;

    public function get(): ?Ecole
    {
        return $this->ecole;
    }

    public function activer(Ecole $ecole): void
    {
        $this->ecole = $ecole;

        config(['database.connections.mysql.database' => $ecole->base_de_donnees]);
        DB::purge('mysql');

        // Fichiers (photos, PDF) rangés par école
        config(['filesystems.disks.local.root' => storage_path('app/private/ecoles/'.$ecole->code)]);
        Storage::forgetDisk('local');

        // Le cache (table « cache » de la base de l'école) et les permissions mises en cache
        app('cache')->forgetDriver();
        config(['permission.cache.key' => 'spatie.permission.cache.'.$ecole->code]);
        $permissions = app(PermissionRegistrar::class);
        $permissions->initializeCache();
        $permissions->clearPermissionsCollection();
    }

    /** Exécute $traitement pour chaque école active (commandes planifiées). */
    public function pourChaque(callable $traitement): void
    {
        foreach (Ecole::where('actif', true)->orderBy('id')->get() as $ecole) {
            $this->activer($ecole);
            $traitement($ecole);
        }
    }
}
