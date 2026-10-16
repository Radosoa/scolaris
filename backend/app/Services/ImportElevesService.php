<?php

namespace App\Services;

use App\Enums\Sexe;
use App\Imports\LectureEleves;
use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\Eleve;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date as DateExcel;
use Throwable;

/**
 * Import d'élèves depuis un fichier Excel / CSV.
 * Colonnes : nom, prenom, sexe, date_naissance, lieu_naissance, classe (facultative).
 * Chaque ligne est validée indépendamment : les lignes valides sont importées,
 * les autres sont listées avec leurs erreurs (numéro de ligne du fichier).
 */
class ImportElevesService
{
    public const MAX_LIGNES = 1000;

    /** Variantes d'en-têtes acceptées (après conversion en slug). */
    private const ALIAS = [
        'date_de_naissance' => 'date_naissance',
        'naissance' => 'date_naissance',
        'lieu_de_naissance' => 'lieu_naissance',
        'prenoms' => 'prenom',
    ];

    public function __construct(
        private readonly EleveService $eleves,
        private readonly InscriptionService $inscriptions,
    ) {}

    /**
     * Analyse le fichier sans rien enregistrer.
     *
     * @return array{total: int, valides: int, lignes: list<array{ligne: int, donnees: array<string, mixed>, erreurs: list<string>}>}
     */
    public function analyser(UploadedFile $fichier): array
    {
        $lignes = $this->lire($fichier);
        $classes = $this->classesAnneeActive();
        $placesRestantes = $classes->mapWithKeys(fn (Classe $c) => [$c->id => $c->capacite - $c->effectif()])->all();
        $dejaVus = [];
        $resultats = [];

        foreach ($lignes as $index => $brute) {
            // +2 : la ligne 1 du fichier contient les en-têtes
            $numero = $index + 2;
            [$donnees, $erreurs] = $this->normaliser($brute, $classes);

            if (! $erreurs) {
                $cle = mb_strtolower("{$donnees['nom']}|{$donnees['prenom']}|{$donnees['date_naissance']}");
                if (isset($dejaVus[$cle])) {
                    $erreurs[] = "Doublon de la ligne {$dejaVus[$cle]} dans le fichier.";
                } elseif ($existant = $this->eleveExistant($donnees)) {
                    $erreurs[] = "Élève déjà enregistré (matricule {$existant->matricule}).";
                }
                $dejaVus[$cle] ??= $numero;
            }

            if (! $erreurs && $donnees['classe_id']) {
                if ($placesRestantes[$donnees['classe_id']] <= 0) {
                    $erreurs[] = "La classe {$donnees['classe']} est complète.";
                } else {
                    $placesRestantes[$donnees['classe_id']]--;
                }
            }

            $resultats[] = ['ligne' => $numero, 'donnees' => $donnees, 'erreurs' => $erreurs];
        }

        return [
            'total' => count($resultats),
            'valides' => count(array_filter($resultats, fn ($r) => ! $r['erreurs'])),
            'lignes' => $resultats,
        ];
    }

    /**
     * Importe les lignes valides (et les inscrit si une classe est indiquée).
     *
     * @return array{total: int, importes: int, erreurs: list<array{ligne: int, erreurs: list<string>}>}
     */
    public function importer(UploadedFile $fichier): array
    {
        $analyse = $this->analyser($fichier);
        $classes = $this->classesAnneeActive()->keyBy('id');

        $importes = DB::transaction(function () use ($analyse, $classes) {
            $total = 0;
            foreach ($analyse['lignes'] as $ligne) {
                if ($ligne['erreurs']) {
                    continue;
                }
                $d = $ligne['donnees'];
                $eleve = $this->eleves->creer([
                    'nom' => $d['nom'],
                    'prenom' => $d['prenom'],
                    'sexe' => $d['sexe'],
                    'date_naissance' => $d['date_naissance'],
                    'lieu_naissance' => $d['lieu_naissance'],
                ]);
                if ($d['classe_id']) {
                    $this->inscriptions->inscrire($eleve, $classes[$d['classe_id']]);
                }
                $total++;
            }

            return $total;
        });

        return [
            'total' => $analyse['total'],
            'importes' => $importes,
            'erreurs' => collect($analyse['lignes'])
                ->filter(fn ($l) => $l['erreurs'])
                ->map(fn ($l) => ['ligne' => $l['ligne'], 'erreurs' => $l['erreurs']])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function lire(UploadedFile $fichier): Collection
    {
        try {
            $feuilles = Excel::toArray(new LectureEleves, $fichier);
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'fichier' => 'Le fichier est illisible. Utilisez le modèle fourni (.xlsx ou .csv).',
            ]);
        }

        $lignes = collect($feuilles[0] ?? [])
            ->map(fn (array $ligne) => collect($ligne)->mapWithKeys(
                fn ($valeur, $cle) => [self::ALIAS[$cle] ?? $cle => is_string($valeur) ? trim($valeur) : $valeur]
            )->all())
            // Lignes entièrement vides (fréquentes en fin de fichier Excel)
            ->reject(fn (array $ligne) => collect($ligne)->filter(fn ($v) => $v !== null && $v !== '')->isEmpty())
            ->values();

        $manquantes = array_diff(['nom', 'prenom', 'sexe', 'date_naissance'], array_keys($lignes->first() ?? []));
        if ($lignes->isNotEmpty() && $manquantes) {
            throw ValidationException::withMessages([
                'fichier' => 'Colonnes manquantes : '.implode(', ', $manquantes).'. Utilisez le modèle fourni.',
            ]);
        }

        if ($lignes->isEmpty()) {
            throw ValidationException::withMessages(['fichier' => 'Le fichier ne contient aucun élève.']);
        }

        if ($lignes->count() > self::MAX_LIGNES) {
            throw ValidationException::withMessages([
                'fichier' => 'Le fichier dépasse '.self::MAX_LIGNES.' lignes : découpez-le en plusieurs imports.',
            ]);
        }

        return $lignes;
    }

    /**
     * @param  array<string, mixed>  $brute
     * @param  Collection<int, Classe>  $classes
     * @return array{0: array<string, mixed>, 1: list<string>}
     */
    private function normaliser(array $brute, Collection $classes): array
    {
        $erreurs = [];
        $classeNom = (string) ($brute['classe'] ?? '');
        $classe = $classeNom === '' ? null
            : $classes->first(fn (Classe $c) => mb_strtolower($c->nom) === mb_strtolower($classeNom));

        $donnees = [
            'nom' => (string) ($brute['nom'] ?? ''),
            'prenom' => (string) ($brute['prenom'] ?? ''),
            'sexe' => $this->sexe($brute['sexe'] ?? null),
            'date_naissance' => $this->date($brute['date_naissance'] ?? null),
            'lieu_naissance' => ($brute['lieu_naissance'] ?? null) ?: null,
            'classe' => $classeNom ?: null,
            'classe_id' => $classe?->id,
        ];

        $validateur = Validator::make($donnees, [
            'nom' => ['required', 'string', 'max:100'],
            'prenom' => ['required', 'string', 'max:100'],
            'sexe' => ['required'],
            'date_naissance' => ['required', 'date', 'before:today', 'after:1990-01-01'],
            'lieu_naissance' => ['nullable', 'string', 'max:100'],
        ], [
            'sexe.required' => 'Sexe invalide : indiquez M ou F.',
            'date_naissance.required' => 'Date de naissance absente ou invalide (format JJ/MM/AAAA).',
        ], [
            'prenom' => 'prénom',
            'date_naissance' => 'date de naissance',
            'lieu_naissance' => 'lieu de naissance',
        ]);

        $erreurs = $validateur->errors()->all();

        if ($classeNom !== '' && ! $classe) {
            $erreurs[] = "Classe « {$classeNom} » introuvable pour l'année active.";
        }

        return [$donnees, $erreurs];
    }

    private function sexe(mixed $valeur): ?string
    {
        $valeur = mb_strtoupper(trim((string) $valeur));

        return match (true) {
            in_array($valeur, ['M', 'MASCULIN', 'G', 'GARÇON', 'GARCON', 'H'], true) => Sexe::Masculin->value,
            in_array($valeur, ['F', 'FÉMININ', 'FEMININ', 'FILLE'], true) => Sexe::Feminin->value,
            default => null,
        };
    }

    /** Date Excel (nombre de jours), JJ/MM/AAAA ou AAAA-MM-JJ → AAAA-MM-JJ. */
    private function date(mixed $valeur): ?string
    {
        if ($valeur === null || $valeur === '') {
            return null;
        }

        if (is_numeric($valeur)) {
            try {
                return CarbonImmutable::instance(DateExcel::excelToDateTimeObject((float) $valeur))->toDateString();
            } catch (Throwable) {
                return null;
            }
        }

        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d', 'd/m/y'] as $format) {
            // DateTimeImmutable renvoie false au lieu de lever une exception (contrairement à Carbon 3)
            $date = \DateTimeImmutable::createFromFormat("!{$format}", (string) $valeur);
            // Le reformatage écarte les dates impossibles que PHP « corrige » (31/02 → 03/03)
            if ($date && $date->format($format) === (string) $valeur) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    private function eleveExistant(array $donnees): ?Eleve
    {
        return Eleve::query()
            ->where('nom', $donnees['nom'])
            ->where('prenom', $donnees['prenom'])
            ->whereDate('date_naissance', $donnees['date_naissance'])
            ->first();
    }

    /**
     * @return Collection<int, Classe>
     */
    private function classesAnneeActive(): Collection
    {
        $annee = AnneeScolaire::active()->first();

        return $annee ? $annee->classes()->with('anneeScolaire')->get() : collect();
    }
}
