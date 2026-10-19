<?php

namespace App\Services\Finances;

use App\Models\Paiement;
use App\Services\ParametresService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

/** Reçu de paiement PDF (gabarit resources/views/pdf/recu.blade.php). */
class RecuPdfService
{
    public function __construct(
        private readonly FinancesService $finances,
        private readonly ParametresService $parametres,
    ) {}

    public function contenu(Paiement $paiement): string
    {
        return Pdf::loadView('pdf.recu', ['ecole' => config('ecole'), 'r' => $this->donnees($paiement)])
            ->setPaper('a5', 'landscape')
            ->output();
    }

    public function nomFichier(Paiement $paiement): string
    {
        $eleve = $paiement->inscription->eleve;

        return sprintf('Recu_%s_%s_%s.pdf', $paiement->numero_recu, Str::slug($eleve->nom), Str::slug($eleve->prenom));
    }

    /** @return array<string, mixed> */
    private function donnees(Paiement $paiement): array
    {
        $paiement->loadMissing('inscription.eleve', 'inscription.classe', 'inscription.anneeScolaire', 'encaissePar', 'annulePar');
        $inscription = $paiement->inscription;

        $total = $this->finances->totalFraisCentimes($inscription);
        // Cumul des paiements valides jusqu'à celui-ci inclus (situation à la date du reçu)
        $cumul = FinancesService::centimes(
            Paiement::valides()
                ->where('inscription_id', $inscription->id)
                ->where(fn ($q) => $q->where('date', '<', $paiement->date)
                    ->orWhere(fn ($m) => $m->where('date', $paiement->date)->where('id', '<=', $paiement->id)))
                ->sum('montant')
        );

        $devise = $this->parametres->devise();
        $euros = fn (int $centimes) => $devise->formater($centimes);

        return [
            'numero' => $paiement->numero_recu,
            'date' => $paiement->date->format('d/m/Y'),
            'annule' => $paiement->estAnnule(),
            'annulation' => $paiement->estAnnule()
                ? sprintf('Annulé le %s%s : %s', $paiement->annule_le->format('d/m/Y'), $paiement->annulePar ? ' par '.$paiement->annulePar->name : '', $paiement->motif_annulation)
                : null,
            'eleve' => mb_strtoupper($inscription->eleve->nom).' '.$inscription->eleve->prenom,
            'matricule' => $inscription->eleve->matricule,
            'classe' => $inscription->classe->nom,
            'annee' => $inscription->anneeScolaire->libelle,
            'montant' => $euros(FinancesService::centimes($paiement->montant)),
            'montant_lettres' => ucfirst(MontantEnLettres::montant($paiement->montant, $devise)),
            'mode' => $paiement->mode->libelle(),
            'reference' => $paiement->reference,
            'total' => $euros($total),
            'cumul' => $euros($cumul),
            'reste' => $euros(max(0, $total - $cumul)),
            'encaisse_par' => $paiement->encaissePar?->name,
            'fait_le' => sprintf('Fait à %s, le %s', config('ecole.ville'), $paiement->created_at->format('d/m/Y')),
        ];
    }
}
