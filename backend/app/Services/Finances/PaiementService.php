<?php

namespace App\Services\Finances;

use App\Enums\ModePaiement;
use App\Exceptions\RegleMetierException;
use App\Models\Inscription;
use App\Models\Paiement;
use App\Models\User;
use App\Services\ParametresService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Encaissement (par tranches libres) et annulation des paiements. */
class PaiementService
{
    public function __construct(
        private readonly FinancesService $finances,
        private readonly ParametresService $parametres,
    ) {}

    /**
     * Enregistre un versement. Il ne peut pas dépasser le reste à payer ; le verrou sur
     * l'inscription empêche deux encaissements simultanés de dépasser le total ensemble.
     *
     * @param  array{montant: float|string, date: string, mode: string, reference?: string|null}  $donnees
     */
    public function encaisser(Inscription $inscription, array $donnees, User $par): Paiement
    {
        return DB::transaction(function () use ($inscription, $donnees, $par) {
            $inscription = Inscription::with('classe', 'anneeScolaire')->lockForUpdate()->findOrFail($inscription->id);

            $total = $this->finances->totalFraisCentimes($inscription);
            if ($total === 0) {
                throw new RegleMetierException('Aucun frais n\'est défini pour ce niveau cette année : rien à encaisser.');
            }

            $reste = $total - $this->finances->payeCentimes($inscription);
            $montant = FinancesService::centimes($donnees['montant']);
            if ($reste <= 0) {
                throw new RegleMetierException('Les frais de cet élève sont déjà entièrement réglés.');
            }
            if ($montant > $reste) {
                throw ValidationException::withMessages([
                    'montant' => 'Le montant dépasse le reste à payer ('.$this->parametres->devise()->formater($reste).').',
                ]);
            }

            $mode = ModePaiement::from($donnees['mode']);
            $paiement = new Paiement([
                'inscription_id' => $inscription->id,
                'montant' => FinancesService::euros($montant),
                'date' => $donnees['date'],
                'mode' => $mode,
                'reference' => $mode->exigeReference() ? ($donnees['reference'] ?? null) : null,
            ]);
            $paiement->forceFill([
                'numero_recu' => $this->prochainNumero($inscription),
                'encaisse_par' => $par->id,
            ])->save();

            return $paiement;
        });
    }

    /** Annule un paiement (erreur de saisie, chèque rejeté…) : il sort des totaux, son reçu reste consultable. */
    public function annuler(Paiement $paiement, string $motif, User $par): Paiement
    {
        if ($paiement->estAnnule()) {
            throw new RegleMetierException("Le paiement {$paiement->numero_recu} est déjà annulé.");
        }

        $paiement->forceFill(['annule_le' => now(), 'annule_par' => $par->id, 'motif_annulation' => $motif])->save();

        return $paiement;
    }

    /**
     * Numéro de reçu R{année}-{séquence} (ex. R2026-00042), jamais réutilisé, même après annulation.
     * Doit être appelé dans une transaction (verrou sur la plage de numéros).
     */
    private function prochainNumero(Inscription $inscription): string
    {
        $prefixe = 'R'.$inscription->anneeScolaire->date_debut->year.'-';

        $dernier = Paiement::query()
            ->where('numero_recu', 'like', $prefixe.'%')
            ->lockForUpdate()
            ->selectRaw('MAX(CAST(SUBSTRING(numero_recu, ?) AS UNSIGNED)) AS numero', [strlen($prefixe) + 1])
            ->value('numero');

        return $prefixe.str_pad((string) ((int) $dernier + 1), 5, '0', STR_PAD_LEFT);
    }
}
