<?php

namespace App\Http\Controllers\Api\Personnes;

use App\Exports\ModeleImportEleves;
use App\Http\Controllers\Controller;
use App\Http\Requests\Personnes\ImportElevesRequest;
use App\Models\Eleve;
use App\Services\ImportElevesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ImportEleveController extends Controller
{
    public function __construct(private readonly ImportElevesService $service) {}

    public function modele(): BinaryFileResponse
    {
        Gate::authorize('create', Eleve::class);

        return Excel::download(new ModeleImportEleves, 'modele-import-eleves.xlsx');
    }

    /** Vérifie le fichier ligne par ligne, sans rien enregistrer. */
    public function analyser(ImportElevesRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->service->analyser($request->file('fichier'))]);
    }

    /** Importe les lignes valides et renvoie le rapport des lignes rejetées. */
    public function importer(ImportElevesRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->service->importer($request->file('fichier'))]);
    }
}
