<?php

namespace Database\Seeders;

use App\Models\Annonce;
use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/** Annonces de démonstration, une par type de cible, dont une programmée. Ne fait rien s'il y en a déjà. */
class AnnoncesSeeder extends Seeder
{
    public function run(): void
    {
        if (Annonce::exists()) {
            return;
        }

        $annee = AnneeScolaire::active()->firstOrFail();
        $direction = User::where('email', 'admin@scolaris.test')->firstOrFail();
        $secretariat = User::where('email', 'secretariat@scolaris.test')->firstOrFail();
        $sixiemeA = Classe::where('annee_scolaire_id', $annee->id)->where('nom', '6e A')->first();
        $troisiemeA = Classe::where('annee_scolaire_id', $annee->id)->where('nom', '3e A')->first();
        $rentree = CarbonImmutable::parse($annee->date_debut)->setTime(8, 0);
        $maintenant = CarbonImmutable::now();

        $annonces = [
            [$direction, 'Bienvenue pour cette nouvelle année scolaire', "Toute l'équipe du collège vous souhaite une excellente rentrée.\n\nLes emplois du temps sont disponibles dans l'application. N'hésitez pas à contacter la vie scolaire pour toute question.", 'tous', null, $rentree],
            [$secretariat, 'Rappel : première tranche des frais de scolarité', "La première tranche (40 % des frais annuels) était à régler avant le 30 septembre.\n\nLes familles qui ne l'ont pas encore fait sont invitées à se rapprocher du secrétariat. Paiement possible par virement, chèque, carte ou espèces.", 'parents', null, $rentree->setDate($rentree->year, 10, 2)->min($maintenant)],
            [$direction, 'Conseil pédagogique du premier trimestre', "Le conseil pédagogique se réunira le jeudi 26 novembre à 17 h en salle des professeurs.\n\nOrdre du jour : bilan de la rentrée, projets interdisciplinaires, préparation des conseils de classe.", 'professeurs', null, $maintenant->subDays(3)],
            [$direction, 'Réunion parents-professeurs', "Les rencontres parents-professeurs auront lieu le mardi 17 novembre de 16 h 30 à 19 h 30.\n\nUn créneau de rendez-vous vous sera proposé par le professeur principal de votre enfant.", 'parents', null, $maintenant->subDays(2)],
            [$secretariat, 'Sortie au musée des Confluences', "Les élèves de 6e A visiteront le musée des Confluences le vendredi 20 novembre.\n\nDépart à 8 h 30 devant le collège, retour vers 16 h. Prévoir un pique-nique et une tenue adaptée.", 'classe', $sixiemeA?->id, $maintenant->subDay()],
            [$direction, 'Brevet blanc', "Le brevet blanc des 3e se déroulera du 8 au 10 décembre. Le planning des épreuves sera communiqué en classe.", 'classe', $troisiemeA?->id, $maintenant->subHours(5)],
            [$direction, 'Vacances de Noël', "Les cours s'arrêteront le vendredi 18 décembre après la classe et reprendront le lundi 4 janvier.\n\nBonnes fêtes de fin d'année à tous !", 'tous', null, $maintenant->addDays(10)],
        ];

        foreach ($annonces as [$auteur, $titre, $contenu, $cible, $classeId, $date]) {
            if ($cible === 'classe' && ! $classeId) {
                continue;
            }
            $annonce = new Annonce(['titre' => $titre, 'contenu' => $contenu, 'cible' => $cible, 'classe_id' => $classeId, 'date_publication' => $date]);
            $annonce->auteur()->associate($auteur)->save();
        }
    }
}
