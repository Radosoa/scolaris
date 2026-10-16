<?php

namespace App\Services;

use App\Enums\StatutInscription;
use App\Enums\TypeAbsence;
use App\Http\Resources\AnnonceResource;
use App\Http\Resources\BulletinResource;
use App\Models\Absence;
use App\Models\AnneeScolaire;
use App\Models\Appel;
use App\Models\Bulletin;
use App\Models\BulletinLigne;
use App\Models\Creneau;
use App\Models\Eleve;
use App\Models\Enseignant;
use App\Models\Inscription;
use App\Models\Note;
use App\Models\Paiement;
use App\Models\Periode;
use App\Models\Tuteur;
use App\Models\User;
use App\Services\Finances\FinancesService;
use App\Services\VieScolaire\AbsenceService;
use App\Services\VieScolaire\AppelService;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

/**
 * Indicateurs des tableaux de bord, un jeu par rôle, sur l'année scolaire active.
 * Les agrégats sont calculés en SQL ; aucun n'est stocké.
 */
class TableauDeBordService
{
    public function __construct(
        private readonly FinancesService $finances,
        private readonly AbsenceService $absences,
        private readonly AppelService $appels,
        private readonly AnnonceService $annonces,
    ) {}

    // ------------------------------------------------------------------ Direction

    /** @return array<string, mixed> */
    public function direction(): array
    {
        $annee = AnneeScolaire::active()->firstOrFail();

        return [
            'annee' => $annee->libelle,
            'compteurs' => [
                'eleves' => $this->inscrits($annee)->count(),
                'classes' => $annee->classes()->count(),
                'enseignants' => Enseignant::whereHas('affectations.classe', fn (Builder $q) => $q->where('annee_scolaire_id', $annee->id))->count(),
            ],
            'effectifs' => $this->effectifsParClasse($annee),
            'resultats' => $this->resultatsParClasse($annee),
            'absenteisme' => $this->absenteisme($annee),
            'finances' => [
                ...$this->finances->totauxAnnee($annee),
                'par_mois' => $this->encaissementsParMois($annee),
            ],
        ];
    }

    /** @return list<array<string, mixed>> effectif, garçons, filles et capacité de chaque classe */
    private function effectifsParClasse(AnneeScolaire $annee): array
    {
        return DB::table('classes')
            ->join('niveaux', 'niveaux.id', '=', 'classes.niveau_id')
            ->leftJoin('inscriptions', fn ($j) => $j->on('inscriptions.classe_id', '=', 'classes.id')->where('inscriptions.statut', StatutInscription::Inscrit->value))
            ->leftJoin('eleves', fn ($j) => $j->on('eleves.id', '=', 'inscriptions.eleve_id')->whereNull('eleves.deleted_at'))
            ->where('classes.annee_scolaire_id', $annee->id)
            ->groupBy('classes.id', 'classes.nom', 'classes.capacite', 'niveaux.ordre')
            ->orderBy('niveaux.ordre')
            ->orderBy('classes.nom')
            ->selectRaw("classes.id, classes.nom, classes.capacite, COUNT(eleves.id) AS effectif,
                SUM(eleves.sexe = 'M') AS garcons, SUM(eleves.sexe = 'F') AS filles")
            ->get()
            ->map(fn ($l) => [
                'classe' => ['id' => $l->id, 'nom' => $l->nom],
                'capacite' => (int) $l->capacite,
                'effectif' => (int) $l->effectif,
                'garcons' => (int) $l->garcons,
                'filles' => (int) $l->filles,
            ])->all();
    }

    /**
     * Moyenne et taux de réussite (moyenne ≥ 10) par classe, sur la dernière période
     * pour laquelle des bulletins ont été calculés.
     *
     * @return array{periode: array{id: int, libelle: string}|null, classes: list<array<string, mixed>>, taux_reussite: float|null, moyenne: float|null}
     */
    private function resultatsParClasse(AnneeScolaire $annee): array
    {
        $periode = Periode::where('annee_scolaire_id', $annee->id)
            ->whereExists(fn ($q) => $q->from('bulletins')->whereColumn('bulletins.periode_id', 'periodes.id'))
            ->orderByDesc('date_debut')
            ->first();

        if (! $periode) {
            return ['periode' => null, 'classes' => [], 'taux_reussite' => null, 'moyenne' => null];
        }

        $base = DB::table('bulletins')
            ->join('inscriptions', 'inscriptions.id', '=', 'bulletins.inscription_id')
            ->join('classes', 'classes.id', '=', 'inscriptions.classe_id')
            ->join('niveaux', 'niveaux.id', '=', 'classes.niveau_id')
            ->where('bulletins.periode_id', $periode->id)
            ->whereNotNull('bulletins.moyenne');

        $classes = (clone $base)
            ->groupBy('classes.id', 'classes.nom', 'niveaux.ordre')
            ->orderBy('niveaux.ordre')
            ->orderBy('classes.nom')
            ->selectRaw('classes.id, classes.nom, AVG(bulletins.moyenne) AS moyenne, MAX(bulletins.moyenne) AS max, MIN(bulletins.moyenne) AS min,
                AVG(bulletins.moyenne >= 10) AS taux, SUM(bulletins.valide) = COUNT(*) AS valides')
            ->get()
            ->map(fn ($l) => [
                'classe' => ['id' => $l->id, 'nom' => $l->nom],
                'moyenne' => BulletinResource::arrondi((float) $l->moyenne),
                'max' => BulletinResource::arrondi((float) $l->max),
                'min' => BulletinResource::arrondi((float) $l->min),
                'taux_reussite' => round((float) $l->taux * 100, 1),
                'valides' => (bool) $l->valides,
            ])->all();

        $global = (clone $base)->selectRaw('AVG(bulletins.moyenne) AS moyenne, AVG(bulletins.moyenne >= 10) AS taux')->first();

        return [
            'periode' => ['id' => $periode->id, 'libelle' => $periode->libelle],
            'classes' => $classes,
            'taux_reussite' => round((float) $global->taux * 100, 1),
            'moyenne' => BulletinResource::arrondi((float) $global->moyenne),
        ];
    }

    /**
     * Heures d'absence (justifiées ou non), retards, taux d'absentéisme (heures d'absence / heures
     * de cours suivies attendues, d'après les appels faits) et évolution par semaine.
     *
     * @return array<string, mixed>
     */
    private function absenteisme(AnneeScolaire $annee): array
    {
        $duree = 'TIME_TO_SEC(TIMEDIFF(absences.heure_fin, absences.heure_debut)) / 3600';
        $absences = Absence::query()
            ->whereBetween('date', [$annee->date_debut->toDateString(), $annee->date_fin->toDateString()])
            ->where('type', TypeAbsence::Absence);

        $totaux = (clone $absences)->selectRaw("COALESCE(SUM(CASE WHEN justifiee THEN {$duree} END), 0) AS justifiees,
            COALESCE(SUM(CASE WHEN NOT justifiee THEN {$duree} END), 0) AS non_justifiees")->toBase()->first();

        // Heures-élèves attendues : durée de chaque cours dont l'appel a été fait × effectif de la classe
        $effectifs = DB::table('inscriptions')->where('statut', StatutInscription::Inscrit->value)
            ->groupBy('classe_id')->selectRaw('classe_id, COUNT(*) AS effectif');
        $attendues = (float) DB::table('appels')
            ->join('creneaux', 'creneaux.id', '=', 'appels.creneau_id')
            ->join('affectations', 'affectations.id', '=', 'creneaux.affectation_id')
            ->joinSub($effectifs, 'e', 'e.classe_id', '=', 'affectations.classe_id')
            ->whereBetween('appels.date', [$annee->date_debut->toDateString(), $annee->date_fin->toDateString()])
            ->sum(DB::raw('TIME_TO_SEC(TIMEDIFF(creneaux.heure_fin, creneaux.heure_debut)) / 3600 * e.effectif'));

        $parSemaine = (clone $absences)
            ->selectRaw("DATE_SUB(date, INTERVAL WEEKDAY(date) DAY) AS semaine,
                SUM(CASE WHEN justifiee THEN {$duree} ELSE 0 END) AS justifiees,
                SUM(CASE WHEN NOT justifiee THEN {$duree} ELSE 0 END) AS non_justifiees")
            ->groupBy('semaine')
            ->orderBy('semaine')
            ->toBase()
            ->get()
            ->map(fn ($l) => [
                'semaine' => substr((string) $l->semaine, 0, 10),
                'justifiees' => round((float) $l->justifiees, 1),
                'non_justifiees' => round((float) $l->non_justifiees, 1),
            ])->all();

        $heures = (float) $totaux->justifiees + (float) $totaux->non_justifiees;

        return [
            'heures_justifiees' => round((float) $totaux->justifiees, 1),
            'heures_non_justifiees' => round((float) $totaux->non_justifiees, 1),
            'retards' => Absence::whereBetween('date', [$annee->date_debut->toDateString(), $annee->date_fin->toDateString()])->where('type', TypeAbsence::Retard)->count(),
            'a_justifier' => Absence::where('justifiee', false)->whereBetween('date', [$annee->date_debut->toDateString(), $annee->date_fin->toDateString()])->count(),
            'taux' => $attendues > 0 ? round($heures / $attendues * 100, 2) : null,
            'par_semaine' => $parSemaine,
        ];
    }

    /** @return list<array{mois: string, montant: float}> encaissements valides de l'année, mois par mois */
    private function encaissementsParMois(AnneeScolaire $annee): array
    {
        return Paiement::valides()
            ->whereHas('inscription', fn (Builder $q) => $q->where('annee_scolaire_id', $annee->id))
            ->selectRaw("DATE_FORMAT(date, '%Y-%m') AS mois, SUM(montant) AS montant")
            ->groupBy('mois')
            ->orderBy('mois')
            ->toBase()
            ->get()
            ->map(fn ($l) => ['mois' => $l->mois, 'montant' => (float) $l->montant])
            ->all();
    }

    // ---------------------------------------------------------------- Secrétariat

    /** @return array<string, mixed> */
    public function secretariat(User $user): array
    {
        $annee = AnneeScolaire::active()->firstOrFail();
        $semaine = CarbonImmutable::today()->subDays(6);

        $recents = Paiement::valides()->whereDate('date', '>=', $semaine);

        return [
            'annee' => $annee->libelle,
            'eleves' => $this->inscrits($annee)->count(),
            'absences' => [
                'a_justifier' => Absence::where('justifiee', false)->count(),
                'aujourdhui' => Absence::whereDate('date', today())->distinct()->count('eleve_id'),
            ],
            'finances' => $this->finances->totauxAnnee($annee),
            'encaissements_7_jours' => ['montant' => (float) (clone $recents)->sum('montant'), 'nombre' => (clone $recents)->count()],
            'derniers_paiements' => Paiement::with('inscription.eleve', 'inscription.classe')->latest('id')->limit(5)->get()
                ->map(fn (Paiement $p) => [
                    'id' => $p->id,
                    'numero_recu' => $p->numero_recu,
                    'date' => $p->date->toDateString(),
                    'montant' => (float) $p->montant,
                    'annule' => $p->estAnnule(),
                    'eleve' => ['id' => $p->inscription->eleve->id, 'nom' => $p->inscription->eleve->nomComplet()],
                    'classe' => $p->inscription->classe->nom,
                ]),
            'annonces' => $this->annoncesRecentes($user),
        ];
    }

    // ----------------------------------------------------------------- Professeur

    /** @return array<string, mixed> */
    public function professeur(User $user): array
    {
        $enseignant = $user->enseignant;
        $aujourdhui = CarbonImmutable::today();

        return [
            'cours_du_jour' => $this->appels->creneauxDuJour($user, $aujourdhui)->map(fn (array $c) => [
                'creneau_id' => $c['creneau']->id,
                'heure_debut' => $c['creneau']->heure_debut,
                'heure_fin' => $c['creneau']->heure_fin,
                'salle' => $c['creneau']->salle,
                'classe' => $c['creneau']->affectation->classe->nom,
                'matiere' => $c['creneau']->affectation->matiere->nom,
                'appel_fait' => $c['appel'] !== null,
            ])->values(),
            'appels_en_attente' => $enseignant ? $this->appelsEnAttente($enseignant, $aujourdhui) : [],
            'classes' => $enseignant ? $this->classesDuProfesseur($enseignant) : [],
            'annonces' => $this->annoncesRecentes($user),
        ];
    }

    /**
     * Cours des 14 derniers jours (avant aujourd'hui) dont l'appel n'a pas été fait.
     *
     * @return list<array{creneau_id: int, date: string, heure_debut: string, classe: string, matiere: string}>
     */
    private function appelsEnAttente(Enseignant $enseignant, CarbonImmutable $aujourdhui): array
    {
        $annee = AnneeScolaire::active()->first();
        if (! $annee) {
            return [];
        }

        $debut = $aujourdhui->subDays(14)->max($annee->date_debut);
        $creneaux = Creneau::with('affectation.classe', 'affectation.matiere')
            ->whereHas('affectation', fn (Builder $q) => $q->where('enseignant_id', $enseignant->id)
                ->whereHas('classe', fn (Builder $c) => $c->where('annee_scolaire_id', $annee->id)))
            ->get()
            ->groupBy('jour');
        $faits = Appel::whereIn('creneau_id', $creneaux->flatten()->pluck('id'))
            ->whereDate('date', '>=', $debut)
            ->get()
            ->map(fn (Appel $a) => $a->creneau_id.'|'.$a->date->toDateString())
            ->flip();

        $manquants = [];
        foreach (CarbonPeriod::create($debut, $aujourdhui->subDay()) as $jour) {
            foreach ($creneaux->get($jour->dayOfWeekIso, collect()) as $creneau) {
                if (! $faits->has($creneau->id.'|'.$jour->toDateString())) {
                    $manquants[] = [
                        'creneau_id' => $creneau->id,
                        'date' => $jour->toDateString(),
                        'heure_debut' => $creneau->heure_debut,
                        'classe' => $creneau->affectation->classe->nom,
                        'matiere' => $creneau->affectation->matiere->nom,
                    ];
                }
            }
        }

        return array_reverse($manquants);
    }

    /** @return list<array<string, mixed>> chaque classe du professeur avec la moyenne de sa matière au dernier bulletin */
    private function classesDuProfesseur(Enseignant $enseignant): array
    {
        return $enseignant->affectations()
            ->with('classe', 'matiere')
            ->whereHas('classe.anneeScolaire', fn (Builder $q) => $q->where('active', true))
            ->get()
            ->sortBy('classe.nom')
            ->map(function ($affectation) {
                $ligne = BulletinLigne::where('matiere_id', $affectation->matiere_id)
                    ->whereHas('bulletin.inscription', fn (Builder $q) => $q->where('classe_id', $affectation->classe_id))
                    ->with('bulletin.periode')
                    ->latest('id')
                    ->first();

                return [
                    'affectation_id' => $affectation->id,
                    'classe' => $affectation->classe->nom,
                    'matiere' => $affectation->matiere->nom,
                    'effectif' => $affectation->classe->effectif(),
                    'moyenne' => BulletinResource::arrondi($ligne?->moyenne_classe),
                    'periode' => $ligne?->bulletin->periode->libelle,
                ];
            })
            ->values()
            ->all();
    }

    // -------------------------------------------------------------- Élève, parent

    /** @return array<string, mixed> */
    public function eleve(User $user): array
    {
        return [
            'eleve' => $user->eleve ? $this->suiviEleve($user->eleve, avecCours: true) : null,
            'annonces' => $this->annoncesRecentes($user),
        ];
    }

    /** @return array<string, mixed> */
    public function parent(User $user): array
    {
        /** @var Tuteur|null $tuteur */
        $tuteur = $user->tuteur;

        return [
            'enfants' => $tuteur
                ? $tuteur->eleves()->orderBy('prenom')->get()->map(fn (Eleve $e) => $this->suiviEleve($e, avecFinances: true))->all()
                : [],
            'annonces' => $this->annoncesRecentes($user),
        ];
    }

    /** @return array<string, mixed> dernier bulletin, notes récentes, absences (et cours du jour / finances) */
    private function suiviEleve(Eleve $eleve, bool $avecCours = false, bool $avecFinances = false): array
    {
        $inscription = $eleve->inscriptionActive()->with('classe', 'anneeScolaire')->first();

        $bulletin = Bulletin::query()
            ->whereHas('inscription', fn (Builder $q) => $q->where('eleve_id', $eleve->id))
            ->where('valide', true)
            ->with('periode')
            ->join('periodes', 'periodes.id', '=', 'bulletins.periode_id')
            ->orderByDesc('periodes.date_debut')
            ->select('bulletins.*')
            ->first();

        $notes = Note::where('eleve_id', $eleve->id)
            ->with('evaluation.affectation.matiere')
            ->join('evaluations', 'evaluations.id', '=', 'notes.evaluation_id')
            ->orderByDesc('evaluations.date')
            ->orderByDesc('notes.id')
            ->select('notes.*')
            ->limit(5)
            ->get();

        return [
            'id' => $eleve->id,
            'nom_complet' => $eleve->nomComplet(),
            'classe' => $inscription?->classe->nom,
            'dernier_bulletin' => $bulletin ? [
                'id' => $bulletin->id,
                'periode' => $bulletin->periode->libelle,
                'moyenne' => BulletinResource::arrondi($bulletin->moyenne),
                'rang' => $bulletin->rang,
                'effectif' => $bulletin->effectif,
                'mention' => $bulletin->mention,
            ] : null,
            'dernieres_notes' => $notes->map(fn (Note $n) => [
                'id' => $n->id,
                'matiere' => $n->evaluation->affectation->matiere->nom,
                'titre' => $n->evaluation->titre,
                'date' => $n->evaluation->date->toDateString(),
                'valeur' => $n->valeur,
                'bareme' => $n->evaluation->bareme,
                'absent' => $n->absent,
            ]),
            'absences' => $this->absences->totaux($eleve),
            'cours_du_jour' => $avecCours && $inscription
                ? Creneau::with('affectation.matiere', 'affectation.enseignant')
                    ->where('jour', today()->dayOfWeekIso)
                    ->whereHas('affectation', fn (Builder $q) => $q->where('classe_id', $inscription->classe_id))
                    ->orderBy('heure_debut')
                    ->get()
                    ->map(fn (Creneau $c) => [
                        'heure_debut' => $c->heure_debut,
                        'heure_fin' => $c->heure_fin,
                        'matiere' => $c->affectation->matiere->nom,
                        'enseignant' => $c->affectation->enseignant->nomComplet(),
                        'salle' => $c->salle,
                    ])
                : null,
            'finances' => $avecFinances && $inscription
                ? (fn (array $s) => ['total' => $s['total'], 'paye' => $s['paye'], 'reste' => $s['reste'], 'retard' => $s['retard']])($this->finances->situation($inscription))
                : null,
        ];
    }

    // ------------------------------------------------------------------- Communs

    /** @return Builder<Inscription> */
    private function inscrits(AnneeScolaire $annee): Builder
    {
        return Inscription::where('annee_scolaire_id', $annee->id)
            ->where('statut', StatutInscription::Inscrit)
            ->whereHas('eleve', fn (Builder $q) => $q->whereNull('eleves.deleted_at'));
    }

    private function annoncesRecentes(User $user): AnonymousResourceCollection
    {
        return AnnonceResource::collection(
            $this->annonces->visiblesPar($user)->where('date_publication', '<=', now())->limit(3)->get()
        );
    }
}
