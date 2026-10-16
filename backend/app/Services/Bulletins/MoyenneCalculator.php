<?php

namespace App\Services\Bulletins;

/**
 * Règles de calcul du bulletin, sans accès à la base : normalisation des notes,
 * moyenne par matière pondérée par catégorie, moyenne générale coefficientée,
 * rangs avec ex æquo, mention et statistiques de classe.
 *
 * Toutes les valeurs restent non arrondies : l'arrondi est réservé à l'affichage.
 */
final class MoyenneCalculator
{
    /**
     * Écart en dessous duquel deux moyennes sont considérées comme égales (ex æquo).
     * Absorbe les erreurs de représentation des flottants (ex. 0.1 + 0.2), sans
     * confondre deux moyennes réellement différentes.
     */
    private const TOLERANCE = 1e-9;

    /**
     * @param  array<string, float>  $ponderations  poids par catégorie (controle_continu, composition)
     * @param  list<array{seuil: float|int, libelle: string}>  $mentions  seuils, dans n'importe quel ordre
     */
    public function __construct(
        private readonly array $ponderations,
        private readonly float $echelle = 20,
        private readonly array $mentions = [],
    ) {}

    public static function depuisConfig(): self
    {
        return new self(
            config('bulletin.ponderations'),
            (float) config('bulletin.echelle'),
            config('bulletin.mentions'),
        );
    }

    /** Note ramenée sur l'échelle du bulletin (sur 20 par défaut). */
    public function normaliser(float $valeur, float $bareme): float
    {
        return $valeur / $bareme * $this->echelle;
    }

    /**
     * Moyenne d'une matière : moyenne simple par catégorie, puis moyenne pondérée des
     * catégories qui ont au moins une note. Les absences ne comptent pas.
     *
     * @param  iterable<array{valeur: float|null, bareme: float, categorie: string, absent: bool}>  $notes
     * @return float|null null si l'élève n'a aucune note comptée dans la matière
     */
    public function moyenneMatiere(iterable $notes): ?float
    {
        $parCategorie = [];
        foreach ($notes as $note) {
            if ($note['absent'] || $note['valeur'] === null || $note['bareme'] <= 0) {
                continue;
            }
            $parCategorie[$note['categorie']][] = $this->normaliser($note['valeur'], $note['bareme']);
        }

        $somme = 0.0;
        $poids = 0.0;
        foreach ($parCategorie as $categorie => $valeurs) {
            $poidsCategorie = (float) ($this->ponderations[$categorie] ?? 0);
            $somme += $poidsCategorie * array_sum($valeurs) / count($valeurs);
            $poids += $poidsCategorie;
        }

        return $poids > 0 ? $somme / $poids : null;
    }

    /**
     * Σ (moyenne × coefficient) / Σ coefficients, sur les seules matières notées.
     *
     * @param  iterable<array{moyenne: float|null, coefficient: float}>  $matieres
     */
    public function moyenneGenerale(iterable $matieres): ?float
    {
        $somme = 0.0;
        $coefficients = 0.0;
        foreach ($matieres as $matiere) {
            if ($matiere['moyenne'] === null || $matiere['coefficient'] <= 0) {
                continue;
            }
            $somme += $matiere['moyenne'] * $matiere['coefficient'];
            $coefficients += $matiere['coefficient'];
        }

        return $coefficients > 0 ? $somme / $coefficients : null;
    }

    /**
     * Classement décroissant ; les ex æquo partagent le rang et le suivant est sauté
     * (1, 2, 2, 4). Les valeurs nulles (non classés) reçoivent un rang null.
     *
     * @template TCle of array-key
     *
     * @param  array<TCle, float|null>  $valeurs
     * @return array<TCle, int|null> dans l'ordre des clés reçues
     */
    public function rangs(array $valeurs): array
    {
        $classes = array_filter($valeurs, fn (?float $v) => $v !== null);
        arsort($classes);

        $rangs = array_map(fn () => null, $valeurs);
        $position = 0;
        $rang = 0;
        $precedente = null;
        foreach ($classes as $cle => $valeur) {
            $position++;
            if ($precedente === null || abs($precedente - $valeur) > self::TOLERANCE) {
                $rang = $position;
            }
            $rangs[$cle] = $rang;
            $precedente = $valeur;
        }

        return $rangs;
    }

    /** Mention correspondant au plus haut seuil atteint. */
    public function mention(?float $moyenne): ?string
    {
        if ($moyenne === null) {
            return null;
        }

        $mentions = $this->mentions;
        usort($mentions, fn (array $a, array $b) => $b['seuil'] <=> $a['seuil']);

        foreach ($mentions as $mention) {
            if ($moyenne + self::TOLERANCE >= $mention['seuil']) {
                return $mention['libelle'];
            }
        }

        return null;
    }

    /**
     * Moyenne, plus forte et plus faible valeur (valeurs nulles ignorées).
     *
     * @param  iterable<float|null>  $valeurs
     * @return array{moyenne: float|null, max: float|null, min: float|null}
     */
    public function statistiques(iterable $valeurs): array
    {
        $valeurs = array_values(array_filter(
            is_array($valeurs) ? $valeurs : iterator_to_array($valeurs, false),
            fn (?float $v) => $v !== null,
        ));

        if ($valeurs === []) {
            return ['moyenne' => null, 'max' => null, 'min' => null];
        }

        return [
            'moyenne' => array_sum($valeurs) / count($valeurs),
            'max' => max($valeurs),
            'min' => min($valeurs),
        ];
    }
}
