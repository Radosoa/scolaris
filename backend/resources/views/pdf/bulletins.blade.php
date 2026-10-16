<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Bulletins</title>
<style>
    @page { margin: 10mm 12mm 13mm; }
    * { box-sizing: border-box; }
    body { font-family: Helvetica, Arial, sans-serif; font-size: 8pt; color: #1f2937; margin: 0; }
    .page { page-break-after: always; position: relative; }
    .page:last-child { page-break-after: auto; }
    table { border-collapse: collapse; width: 100%; }
    .muted { color: #6b7280; }
    .right { text-align: right; }
    .center { text-align: center; }
    .bold { font-weight: bold; }

    .entete td { vertical-align: top; padding: 0; }
    .entete td.monogramme { width: 14mm; height: 14mm; background: #1e3a8a; color: #fff; font-size: 15pt; font-weight: bold; text-align: center; vertical-align: middle; }
    .ecole { font-size: 12pt; font-weight: bold; color: #1e3a8a; }
    .titre { margin: 4mm 0 3mm; padding: 2mm 0; text-align: center; font-size: 11.5pt; font-weight: bold; letter-spacing: 0.5pt; border-top: 0.6pt solid #1e3a8a; border-bottom: 0.6pt solid #1e3a8a; color: #1e3a8a; }

    .identite td { padding: 1mm 2mm; border: 0.5pt solid #d1d5db; }
    .identite .libelle { color: #6b7280; width: 22mm; background: #f3f4f6; }

    .notes { margin-top: 3mm; }
    .notes th { background: #1e3a8a; color: #fff; font-weight: bold; font-size: 7.5pt; padding: 1.6mm 1.2mm; border: 0.5pt solid #1e3a8a; }
    .notes td { padding: 1mm 1.2mm; border: 0.5pt solid #d1d5db; vertical-align: top; }
    .notes tbody tr:nth-child(even) td { background: #f9fafb; }
    .notes .matiere { font-weight: bold; }
    .notes .prof { font-size: 7pt; color: #6b7280; }
    .notes .appreciation { font-size: 7pt; font-style: italic; }
    .notes tfoot td { background: #eef2ff; font-weight: bold; }

    .bilan { margin-top: 3mm; }
    .bilan > tbody > tr > td { vertical-align: top; padding: 0; }
    .encadre { border: 0.6pt solid #1e3a8a; padding: 2.5mm 3mm; }
    .encadre h3 { margin: 0 0 1.5mm; font-size: 8.5pt; color: #1e3a8a; text-transform: uppercase; letter-spacing: 0.3pt; }
    .chiffre { font-size: 15pt; font-weight: bold; color: #1e3a8a; }
    .mention { display: inline-block; margin-top: 1.5mm; padding: 0.8mm 2.5mm; background: #1e3a8a; color: #fff; font-weight: bold; border-radius: 1mm; }
    .kv td { padding: 0.6mm 0; }

    .signatures { margin-top: 4mm; }
    .signatures td { width: 33%; vertical-align: top; padding-right: 3mm; }
    .signature { height: 13mm; border-bottom: 0.5pt dotted #9ca3af; }

    .projet { position: absolute; top: 95mm; left: 0; right: 0; text-align: center; font-size: 46pt; font-weight: bold; color: #dc2626; opacity: 0.12; transform: rotate(-30deg); }
    .pied { position: fixed; bottom: -8mm; left: 0; right: 0; font-size: 6.5pt; color: #9ca3af; text-align: center; }
</style>
</head>
<body>
<div class="pied">{{ $ecole['nom'] }} · {{ $ecole['adresse'] }} · {{ $ecole['telephone'] }} · {{ $ecole['email'] }}</div>

@foreach ($bulletins as $b)
<div class="page">
    @unless ($b['valide'])
        <div class="projet">PROJET — NON VALIDÉ</div>
    @endunless

    <table class="entete">
        <tr>
            <td style="width: 19mm"><table style="width: 14mm"><tr><td class="monogramme">{{ mb_substr($ecole['nom'], 0, 1) }}</td></tr></table></td>
            <td>
                <div class="ecole">{{ $ecole['nom'] }}</div>
                <div class="muted">{{ $ecole['adresse'] }}</div>
                <div class="muted">Tél. {{ $ecole['telephone'] }} · {{ $ecole['email'] }}</div>
            </td>
            <td class="right">
                <div class="bold">Année scolaire {{ $b['annee'] }}</div>
                <div class="muted">{{ $b['periode'] }}</div>
            </td>
        </tr>
    </table>

    <div class="titre">BULLETIN DE NOTES — {{ mb_strtoupper($b['periode']) }}</div>

    <table class="identite">
        <tr>
            <td class="libelle">Élève</td>
            <td class="bold">{{ $b['eleve']['nom'] }}</td>
            <td class="libelle">Classe</td>
            <td class="bold">{{ $b['classe'] }}</td>
        </tr>
        <tr>
            <td class="libelle">Matricule</td>
            <td>{{ $b['eleve']['matricule'] }}</td>
            <td class="libelle">Effectif</td>
            <td>{{ $b['effectif'] }} élèves</td>
        </tr>
        @if ($b['eleve']['naissance'])
        <tr>
            <td class="libelle">Naissance</td>
            <td colspan="3">{{ $b['eleve']['naissance'] }}</td>
        </tr>
        @endif
    </table>

    <table class="notes">
        <thead>
            <tr>
                <th style="text-align: left; width: 40mm">Matière</th>
                <th style="width: 9mm">Coef.</th>
                <th style="width: 12mm">Moy.</th>
                <th style="width: 13mm">Points</th>
                <th style="width: 12mm">Moy. classe</th>
                <th style="width: 10mm">Min</th>
                <th style="width: 10mm">Max</th>
                <th style="width: 12mm">Rang</th>
                <th style="text-align: left">Appréciation</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($b['lignes'] as $l)
            <tr>
                <td>
                    <div class="matiere">{{ $l['matiere'] }}</div>
                    @if ($l['enseignant'])<div class="prof">{{ $l['enseignant'] }}</div>@endif
                </td>
                <td class="center">{{ $l['coefficient'] }}</td>
                <td class="center bold">{{ $l['moyenne'] }}</td>
                <td class="center">{{ $l['points'] }}</td>
                <td class="center muted">{{ $l['moyenne_classe'] }}</td>
                <td class="center muted">{{ $l['min'] }}</td>
                <td class="center muted">{{ $l['max'] }}</td>
                <td class="center">{{ $l['rang'] }}</td>
                <td class="appreciation">{{ $l['appreciation'] }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td>Total</td>
                <td class="center">{{ $b['total_coefficients'] }}</td>
                <td></td>
                <td class="center">{{ $b['total_points'] }}</td>
                <td colspan="5" class="muted" style="font-weight: normal; font-size: 7pt">
                    Moyennes sur 20. Matières non notées exclues de la moyenne générale.
                </td>
            </tr>
        </tfoot>
    </table>

    <table class="bilan">
        <tr>
            <td style="width: 38%; padding-right: 3mm">
                <div class="encadre">
                    <h3>Résultats</h3>
                    <div><span class="chiffre">{{ $b['moyenne'] }}</span> <span class="muted">/ 20</span></div>
                    <div>Rang : <span class="bold">{{ $b['rang'] }}</span> sur {{ $b['effectif'] }}</div>
                    @if ($b['mention'])<div class="mention">{{ $b['mention'] }}</div>@endif
                </div>
            </td>
            <td style="width: 31%; padding-right: 3mm">
                <div class="encadre">
                    <h3>Classe</h3>
                    <table class="kv">
                        <tr><td class="muted">Moyenne</td><td class="right bold">{{ $b['moyenne_classe'] }}</td></tr>
                        <tr><td class="muted">Plus forte</td><td class="right">{{ $b['moyenne_max'] }}</td></tr>
                        <tr><td class="muted">Plus faible</td><td class="right">{{ $b['moyenne_min'] }}</td></tr>
                    </table>
                </div>
            </td>
            <td>
                <div class="encadre">
                    <h3>Vie scolaire</h3>
                    <table class="kv">
                        <tr><td class="muted">Absences justifiées</td><td class="right">{{ $b['absences']['justifiees'] }}</td></tr>
                        <tr><td class="muted">Non justifiées</td><td class="right bold">{{ $b['absences']['non_justifiees'] }}</td></tr>
                        <tr><td class="muted">Retards</td><td class="right">{{ $b['absences']['retards'] }}</td></tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <div class="encadre" style="margin-top: 3mm; min-height: 13mm">
        <h3>Appréciation générale</h3>
        <div>{{ $b['appreciation_generale'] ?? '' }}</div>
    </div>

    <table class="signatures">
        <tr>
            <td>
                <div class="muted">{{ $b['fait_le'] }}</div>
                <div class="bold" style="margin-top: 1mm">{{ $ecole['chef_etablissement'] }}</div>
                <div class="signature"></div>
            </td>
            <td>
                <div class="muted">&nbsp;</div>
                <div class="bold" style="margin-top: 1mm">Le professeur principal</div>
                <div class="signature"></div>
            </td>
            <td>
                <div class="muted">&nbsp;</div>
                <div class="bold" style="margin-top: 1mm">Signature des parents</div>
                <div class="signature"></div>
            </td>
        </tr>
    </table>
</div>
@endforeach
</body>
</html>
