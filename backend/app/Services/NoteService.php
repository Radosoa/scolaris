<?php

namespace App\Services;

use App\Enums\StatutInscription;
use App\Models\Eleve;
use App\Models\Evaluation;
use App\Models\Note;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class NoteService
{
    public function __construct(private readonly EvaluationService $evaluations) {}

    /**
     * Grille de saisie : élèves inscrits dans la classe, plus ceux qui ont déjà une note
     * (ex. élève transféré en cours de période), triés par nom.
     *
     * @return Collection<int, array{eleve_id: int, matricule: string, nom: string, prenom: string, inscrit: bool, valeur: float|null, absent: bool}>
     */
    public function grille(Evaluation $evaluation): Collection
    {
        $classeId = $evaluation->affectation->classe_id;
        $notes = $evaluation->notes()->get()->keyBy('eleve_id');

        $eleves = Eleve::withTrashed()
            ->where(fn (Builder $q) => $q
                ->whereHas('inscriptions', fn (Builder $i) => $i->where('classe_id', $classeId)->where('statut', StatutInscription::Inscrit))
                ->orWhereIn('id', $notes->keys()))
            ->with(['inscriptions' => fn ($q) => $q->where('classe_id', $classeId)])
            ->orderBy('nom')
            ->orderBy('prenom')
            ->get();

        return $eleves->map(fn (Eleve $eleve) => [
            'eleve_id' => $eleve->id,
            'matricule' => $eleve->matricule,
            'nom' => $eleve->nom,
            'prenom' => $eleve->prenom,
            'inscrit' => $eleve->inscriptions->contains(fn ($i) => $i->statut === StatutInscription::Inscrit),
            'valeur' => $notes[$eleve->id]->valeur ?? null,
            'absent' => (bool) ($notes[$eleve->id]->absent ?? false),
        ]);
    }

    /**
     * Enregistre un lot de notes en une transaction. Pour chaque élève :
     * - absent = true  → note « absent » (valeur ignorée) ;
     * - valeur saisie  → note enregistrée ;
     * - ni l'un ni l'autre → note effacée (non saisie).
     *
     * @param  list<array{eleve_id: int, valeur: float|null, absent?: bool}>  $lignes
     */
    public function enregistrerLot(Evaluation $evaluation, array $lignes): void
    {
        $this->evaluations->refuserSiVerrouillee($evaluation);

        $autorises = $this->grille($evaluation)->pluck('eleve_id')->flip();
        $erreurs = [];
        foreach ($lignes as $index => $ligne) {
            if (! $autorises->has($ligne['eleve_id'])) {
                $erreurs["notes.{$index}.eleve_id"] = 'Cet élève n\'appartient pas à la classe évaluée.';
            }
        }
        if ($erreurs) {
            throw ValidationException::withMessages($erreurs);
        }

        DB::transaction(function () use ($evaluation, $lignes) {
            foreach ($lignes as $ligne) {
                $absent = (bool) ($ligne['absent'] ?? false);
                $valeur = $absent ? null : $ligne['valeur'];

                if (! $absent && $valeur === null) {
                    Note::where('evaluation_id', $evaluation->id)->where('eleve_id', $ligne['eleve_id'])->delete();

                    continue;
                }

                Note::updateOrCreate(
                    ['evaluation_id' => $evaluation->id, 'eleve_id' => $ligne['eleve_id']],
                    ['valeur' => $valeur, 'absent' => $absent],
                );
            }
        });
    }

    /**
     * Statistiques de l'évaluation (absents exclus).
     *
     * @return array{saisies: int, absents: int, moyenne: float|null, min: float|null, max: float|null}
     */
    public function statistiques(Evaluation $evaluation): array
    {
        $notes = $evaluation->notes()->get();
        $valeurs = $notes->where('absent', false)->pluck('valeur')->filter(fn ($v) => $v !== null);

        return [
            'saisies' => $notes->count(),
            'absents' => $notes->where('absent', true)->count(),
            'moyenne' => $valeurs->isEmpty() ? null : round($valeurs->avg(), 2),
            'min' => $valeurs->min(),
            'max' => $valeurs->max(),
        ];
    }
}
