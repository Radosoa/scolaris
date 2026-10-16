<?php

namespace App\Jobs;

use App\Models\Classe;
use App\Models\Periode;
use App\Services\Bulletins\BulletinPdfService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Génère les PDF des bulletins validés d'une classe, plus le PDF regroupé.
 * Avec QUEUE_CONNECTION=sync (hébergement sans worker), il s'exécute dans la requête.
 */
class GenererBulletinsClasse implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 2;

    public function __construct(public readonly int $classeId, public readonly int $periodeId) {}

    /** Indicateur « génération en cours » affiché par l'écran de la direction. */
    public static function cleEnCours(int $classeId, int $periodeId): string
    {
        return "bulletins-pdf-en-cours:{$classeId}:{$periodeId}";
    }

    public function handle(BulletinPdfService $pdf): void
    {
        $pdf->genererClasse(Classe::findOrFail($this->classeId), Periode::findOrFail($this->periodeId));

        Cache::forget(self::cleEnCours($this->classeId, $this->periodeId));
    }

    public function failed(?Throwable $exception): void
    {
        Cache::forget(self::cleEnCours($this->classeId, $this->periodeId));
    }
}
