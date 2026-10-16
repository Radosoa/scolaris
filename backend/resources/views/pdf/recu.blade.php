<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Reçu {{ $r['numero'] }}</title>
<style>
    @page { margin: 10mm 12mm; }
    body { font-family: Helvetica, Arial, sans-serif; font-size: 9pt; color: #1f2937; margin: 0; }
    table { border-collapse: collapse; width: 100%; }
    .muted { color: #6b7280; }
    .right { text-align: right; }
    .bold { font-weight: bold; }
    .entete td { vertical-align: top; padding: 0; }
    .entete td.monogramme { width: 12mm; height: 12mm; background: #1e3a8a; color: #fff; font-size: 13pt; font-weight: bold; text-align: center; vertical-align: middle; }
    .ecole { font-size: 11pt; font-weight: bold; color: #1e3a8a; }
    .titre { margin: 4mm 0 3mm; padding: 1.8mm 0; border-top: 0.6pt solid #1e3a8a; border-bottom: 0.6pt solid #1e3a8a; }
    .titre td { font-size: 11pt; font-weight: bold; color: #1e3a8a; }
    .infos td { padding: 1.2mm 2mm; border: 0.5pt solid #d1d5db; }
    .infos .libelle { color: #6b7280; background: #f3f4f6; width: 30mm; }
    .montant { margin-top: 3mm; border: 0.8pt solid #1e3a8a; padding: 3mm; }
    .montant .chiffre { font-size: 16pt; font-weight: bold; color: #1e3a8a; }
    .situation { margin-top: 3mm; }
    .situation td { padding: 1mm 2mm; }
    .situation .total td { border-top: 0.5pt solid #1e3a8a; font-weight: bold; }
    .signature { height: 14mm; border-bottom: 0.5pt dotted #9ca3af; }
    .annule { position: absolute; top: 45mm; left: 0; right: 0; text-align: center; font-size: 54pt; font-weight: bold; color: #dc2626; opacity: 0.18; transform: rotate(-18deg); }
</style>
</head>
<body>
@if ($r['annule'])
    <div class="annule">ANNULÉ</div>
@endif

<table class="entete">
    <tr>
        <td style="width: 16mm"><table style="width: 12mm"><tr><td class="monogramme">{{ mb_substr($ecole['nom'], 0, 1) }}</td></tr></table></td>
        <td>
            <div class="ecole">{{ $ecole['nom'] }}</div>
            <div class="muted">{{ $ecole['adresse'] }} · Tél. {{ $ecole['telephone'] }}</div>
        </td>
        <td class="right">
            <div class="bold">Année scolaire {{ $r['annee'] }}</div>
            <div class="muted">{{ $ecole['email'] }}</div>
        </td>
    </tr>
</table>

<table class="titre">
    <tr>
        <td>REÇU DE PAIEMENT</td>
        <td class="right">N° {{ $r['numero'] }}</td>
    </tr>
</table>

<table>
    <tr>
        <td style="width: 58%; vertical-align: top; padding-right: 4mm">
            <table class="infos">
                <tr><td class="libelle">Élève</td><td class="bold">{{ $r['eleve'] }}</td></tr>
                <tr><td class="libelle">Matricule</td><td>{{ $r['matricule'] }}</td></tr>
                <tr><td class="libelle">Classe</td><td>{{ $r['classe'] }}</td></tr>
                <tr><td class="libelle">Date du paiement</td><td>{{ $r['date'] }}</td></tr>
                <tr><td class="libelle">Mode</td><td>{{ $r['mode'] }}@if ($r['reference']) · réf. {{ $r['reference'] }}@endif</td></tr>
            </table>

            <div class="montant">
                <div class="muted">Montant reçu</div>
                <div class="chiffre">{{ $r['montant'] }}</div>
                <div>{{ $r['montant_lettres'] }}</div>
            </div>
            @if ($r['annulation'])
                <p class="bold" style="color: #dc2626">{{ $r['annulation'] }}</p>
            @endif
        </td>
        <td style="vertical-align: top">
            <table class="situation">
                <tr><td class="muted">Frais de l'année</td><td class="right">{{ $r['total'] }}</td></tr>
                <tr><td class="muted">Total réglé à cette date</td><td class="right">{{ $r['cumul'] }}</td></tr>
                <tr class="total"><td>Reste à payer</td><td class="right">{{ $r['reste'] }}</td></tr>
            </table>

            <div style="margin-top: 6mm">
                <div class="muted">{{ $r['fait_le'] }}</div>
                <div class="bold">Encaissé par {{ $r['encaisse_par'] ?? 'le secrétariat' }}</div>
                <div class="signature"></div>
                <div class="muted" style="font-size: 7pt; margin-top: 1mm">Signature et cachet de l'établissement</div>
            </div>
        </td>
    </tr>
</table>
</body>
</html>
