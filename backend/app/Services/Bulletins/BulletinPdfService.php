<?php

namespace App\Services\Bulletins;

use App\Enums\Sexe;
use App\Exceptions\RegleMetierException;
use App\Jobs\GenererBulletinsClasse;
use App\Models\Bulletin;
use App\Models\BulletinLigne;
use App\Models\Classe;
use App\Models\Periode;
use App\Services\ParametresService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Rendu PDF des bulletins (gabarit resources/views/pdf/bulletins.blade.php), en police
 * Helvetica standard (non embarquée) : ~7 Ko et ~0,3 s par bulletin, accents français inclus.
 * Les PDF des bulletins validés sont stockés sur le disque privé `local` ;
 * un brouillon est rendu à la volée avec la mention « Projet ».
 */
class BulletinPdfService
{
    public function __construct(private readonly ParametresService $parametres) {}

    /** Relations nécessaires au gabarit. */
    private const RELATIONS = [
        'lignes.matiere',
        'inscription.eleve',
        'inscription.classe.affectations.enseignant',
        'periode.anneeScolaire',
    ];

    /** Contenu PDF d'un bulletin (brouillon ou validé). */
    public function contenu(Bulletin $bulletin): string
    {
        return $this->rendre(collect([$bulletin]));
    }

    /** Chemin du PDF stocké d'un bulletin validé, généré s'il n'existe pas encore. */
    public function fichier(Bulletin $bulletin): string
    {
        if ($bulletin->pdf_path && Storage::exists($bulletin->pdf_path)) {
            return $bulletin->pdf_path;
        }

        $bulletin->loadMissing(self::RELATIONS);
        $chemin = sprintf('bulletins/periode-%d/%s.pdf', $bulletin->periode_id, $bulletin->inscription->eleve->matricule);
        Storage::put($chemin, $this->contenu($bulletin));
        $bulletin->forceFill(['pdf_path' => $chemin])->save();

        return $chemin;
    }

    /**
     * Génère le PDF de chaque bulletin validé de la classe et un PDF unique
     * regroupant toute la classe (pour l'impression).
     */
    public function genererClasse(Classe $classe, Periode $periode): int
    {
        $bulletins = Bulletin::deClasse($classe, $periode)
            ->where('valide', true)
            ->with(self::RELATIONS)
            ->get()
            ->sortBy(fn (Bulletin $b) => [$b->inscription->eleve->nom, $b->inscription->eleve->prenom])
            ->values();

        foreach ($bulletins as $bulletin) {
            $this->fichier($bulletin);
        }

        Storage::put($this->cheminClasse($classe, $periode), $this->rendre($bulletins));

        return $bulletins->count();
    }

    /** Lance la génération des PDF de la classe en tâche de fond (job de queue). */
    public function lancerGenerationClasse(Classe $classe, Periode $periode): void
    {
        if (! Bulletin::deClasse($classe, $periode)->where('valide', true)->exists()) {
            throw new RegleMetierException('Validez les bulletins de la classe avant de générer les PDF.');
        }

        Cache::put(GenererBulletinsClasse::cleEnCours($classe->id, $periode->id), true, now()->addMinutes(15));
        GenererBulletinsClasse::dispatch($classe->id, $periode->id);
    }

    /** @return array{pdf_en_cours: bool, pdf_classe: bool} */
    public function etatClasse(Classe $classe, Periode $periode): array
    {
        return [
            'pdf_en_cours' => Cache::has(GenererBulletinsClasse::cleEnCours($classe->id, $periode->id)),
            'pdf_classe' => Storage::exists($this->cheminClasse($classe, $periode)),
        ];
    }

    public function cheminClasse(Classe $classe, Periode $periode): string
    {
        return sprintf('bulletins/periode-%d/classe-%d.pdf', $periode->id, $classe->id);
    }

    /** Ex. « Bulletin_Trimestre-1_Petit_Lucas.pdf ». */
    public function nomFichier(Bulletin $bulletin): string
    {
        $eleve = $bulletin->inscription->eleve;

        return sprintf('Bulletin_%s_%s_%s.pdf', Str::slug($bulletin->periode->libelle), Str::slug($eleve->nom), Str::slug($eleve->prenom));
    }

    public function nomFichierClasse(Classe $classe, Periode $periode): string
    {
        return sprintf('Bulletins_%s_%s.pdf', Str::slug($classe->nom), Str::slug($periode->libelle));
    }

    /** @param  Collection<int, Bulletin>  $bulletins */
    private function rendre(Collection $bulletins): string
    {
        $bulletins->each->loadMissing(self::RELATIONS);

        return Pdf::loadView('pdf.bulletins', [
            'ecole' => $this->parametres->identite(),
            'bulletins' => $bulletins->map(fn (Bulletin $b) => $this->donnees($b)),
        ])->setPaper('a4')->output();
    }

    /**
     * Données déjà formatées pour le gabarit (aucun calcul dans la vue).
     *
     * @return array<string, mixed>
     */
    private function donnees(Bulletin $bulletin): array
    {
        $eleve = $bulletin->inscription->eleve;
        $classe = $bulletin->inscription->classe;
        $enseignants = $classe->affectations->mapWithKeys(fn ($a) => [$a->matiere_id => $a->enseignant->nomComplet()]);

        $lignes = $bulletin->lignes->sortBy('matiere.nom')->values();
        $notees = $lignes->filter(fn (BulletinLigne $l) => $l->moyenne !== null);

        return [
            'valide' => $bulletin->valide,
            'periode' => $bulletin->periode->libelle,
            'annee' => $bulletin->periode->anneeScolaire->libelle,
            'eleve' => [
                'nom' => mb_strtoupper($eleve->nom).' '.$eleve->prenom,
                'matricule' => $eleve->matricule,
                'naissance' => $eleve->date_naissance
                    ? sprintf('%s le %s%s', $eleve->sexe === Sexe::Feminin ? 'Née' : 'Né', $eleve->date_naissance->format('d/m/Y'), $eleve->lieu_naissance ? ' à '.$eleve->lieu_naissance : '')
                    : null,
            ],
            'classe' => $classe->nom,
            'effectif' => $bulletin->effectif,
            'lignes' => $lignes->map(fn (BulletinLigne $l) => [
                'matiere' => $l->matiere->nom,
                'enseignant' => $enseignants[$l->matiere_id] ?? null,
                'coefficient' => $this->nombre($l->coefficient),
                'moyenne' => $this->note($l->moyenne),
                'points' => $l->moyenne === null ? '—' : $this->note($l->moyenne * $l->coefficient),
                'moyenne_classe' => $this->note($l->moyenne_classe),
                'min' => $this->note($l->moyenne_min),
                'max' => $this->note($l->moyenne_max),
                'rang' => $this->rang($l->rang_matiere, nonClasse: '—'),
                'appreciation' => $l->appreciation,
            ]),
            'total_coefficients' => $this->nombre($notees->sum('coefficient')),
            'total_points' => $this->note($notees->sum(fn (BulletinLigne $l) => $l->moyenne * $l->coefficient)),
            'moyenne' => $this->note($bulletin->moyenne),
            'rang' => $this->rang($bulletin->rang, $bulletin->estExAequo()),
            'mention' => $bulletin->mention,
            'moyenne_classe' => $this->note($bulletin->moyenne_classe),
            'moyenne_max' => $this->note($bulletin->moyenne_max),
            'moyenne_min' => $this->note($bulletin->moyenne_min),
            'absences' => [
                'justifiees' => $this->nombre($bulletin->heures_abs_justifiees).' h',
                'non_justifiees' => $this->nombre($bulletin->heures_abs_non_justifiees).' h',
                'retards' => $bulletin->nb_retards,
            ],
            'appreciation_generale' => $bulletin->appreciation_generale,
            'fait_le' => sprintf('Fait à %s, le %s', $this->parametres->identite()['ville'], ($bulletin->valide_le ?? now())->format('d/m/Y')),
        ];
    }

    /** Moyenne arrondie à l'affichage (2 décimales par défaut), virgule décimale. */
    private function note(?float $valeur): string
    {
        return $valeur === null ? '—' : number_format($valeur, config('bulletin.decimales_affichage'), ',', ' ');
    }

    private function nombre(float $valeur): string
    {
        return rtrim(rtrim(number_format($valeur, 2, ',', ' '), '0'), ',');
    }

    /** 1er, 2e… avec mention ex æquo. */
    private function rang(?int $rang, bool $exAequo = false, string $nonClasse = 'Non classé'): string
    {
        if ($rang === null) {
            return $nonClasse;
        }

        return ($rang === 1 ? '1er' : $rang.'e').($exAequo ? ' ex æquo' : '');
    }
}
