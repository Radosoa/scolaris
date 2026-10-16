<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Affectation;
use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\Enseignant;
use App\Models\Matiere;
use App\Models\Tuteur;
use App\Models\User;
use App\Services\EleveService;
use App\Services\InscriptionService;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;

/**
 * Population de démonstration : un enseignant par matière (affecté à toutes les classes
 * où la matière est enseignée), ~24 élèves par classe avec leurs parents, et rattachement
 * des comptes de démo professeur@, eleve@ et parent@ à de vrais profils.
 * Ne fait rien si des élèves existent déjà.
 */
class PersonnesSeeder extends Seeder
{
    private const ELEVES_PAR_CLASSE = 24;

    /** Code matière => [prénom, nom] de l'enseignant ; MATH = compte de démo professeur@ */
    private const ENSEIGNANTS = [
        'MATH' => ['Julien', 'Moreau'],
        'FR' => ['Isabelle', 'Lambert'],
        'ANG' => ['Thomas', 'Girard'],
        'ESP' => ['Carmen', 'Roux'],
        'HG' => ['Philippe', 'Mercier'],
        'SVT' => ['Aurélie', 'Faure'],
        'PC' => ['Nicolas', 'Blanc'],
        'TECH' => ['Sébastien', 'Guerin'],
        'EPS' => ['Mathieu', 'Chevalier'],
        'ART' => ['Camille', 'Perrin'],
    ];

    public function run(EleveService $eleves, InscriptionService $inscriptions): void
    {
        if (Eleve::exists()) {
            $this->command?->info('Des élèves existent déjà : PersonnesSeeder ignoré.');

            return;
        }

        $annee = AnneeScolaire::active()->firstOrFail();
        $classes = $annee->classes()->with('niveau.coefficients')->get();
        $faker = Faker::create('fr_FR');
        $faker->seed(2026);

        $this->creerEnseignants($classes);

        foreach ($classes as $classe) {
            // Âge cohérent avec le niveau : 11 ans en 6e, 14 ans en 3e
            $anneeNaissance = $annee->date_debut->year - 10 - $classe->niveau->ordre;

            foreach (range(1, self::ELEVES_PAR_CLASSE) as $_) {
                $sexe = $faker->randomElement(['M', 'F']);
                $nom = $faker->lastName();
                $eleve = $eleves->creer([
                    'nom' => $nom,
                    'prenom' => $faker->firstName($sexe === 'M' ? 'male' : 'female'),
                    'sexe' => $sexe,
                    'date_naissance' => $faker->dateTimeBetween("{$anneeNaissance}-01-01", "{$anneeNaissance}-12-31")->format('Y-m-d'),
                    'lieu_naissance' => $faker->city(),
                ]);
                $this->creerParents($eleve, $nom, $faker);
                $inscriptions->inscrire($eleve, $classe, $annee->date_debut->toDateString());
            }
        }

        $this->rattacherComptesDemo($classes->firstWhere('nom', '6e A'), $eleves, $inscriptions);
    }

    /** @param \Illuminate\Support\Collection<int, Classe> $classes */
    private function creerEnseignants($classes): void
    {
        foreach (self::ENSEIGNANTS as $code => [$prenom, $nom]) {
            $matiere = Matiere::where('code', $code)->first();
            if (! $matiere) {
                continue;
            }

            $user = $code === 'MATH'
                ? User::where('email', 'professeur@scolaris.test')->firstOrFail()
                : User::create([
                    'name' => "{$prenom} {$nom}",
                    'email' => mb_strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', "{$prenom}.{$nom}")).'@scolaris.test',
                    'password' => DemoUserSeeder::PASSWORD,
                ])->assignRole(Role::Professeur->value);

            $enseignant = new Enseignant(['nom' => $nom, 'prenom' => $prenom, 'specialite' => $matiere->nom]);
            $enseignant->user()->associate($user)->save();

            foreach ($classes as $classe) {
                if ($classe->niveau->coefficients->contains('matiere_id', $matiere->id)) {
                    Affectation::create(['enseignant_id' => $enseignant->id, 'matiere_id' => $matiere->id, 'classe_id' => $classe->id]);
                }
            }
        }
    }

    private function creerParents(Eleve $eleve, string $nom, \Faker\Generator $faker): void
    {
        $mere = Tuteur::create([
            'nom' => $faker->boolean(80) ? $nom : $faker->lastName(),
            'prenom' => $faker->firstName('female'),
            'telephone' => $faker->numerify('06 ## ## ## ##'),
            'email' => $faker->boolean(70) ? $faker->unique()->safeEmail() : null,
        ]);
        $liens = [$mere->id => ['lien' => 'mere']];

        if ($faker->boolean(75)) {
            $pere = Tuteur::create([
                'nom' => $nom,
                'prenom' => $faker->firstName('male'),
                'telephone' => $faker->numerify('07 ## ## ## ##'),
                'email' => null,
            ]);
            $liens[$pere->id] = ['lien' => 'pere'];
        }

        $eleve->tuteurs()->sync($liens);
    }

    /** Lucas Petit (eleve@) en 6e A, enfant de Nathalie Petit (parent@). */
    private function rattacherComptesDemo(?Classe $classe, EleveService $eleves, InscriptionService $inscriptions): void
    {
        if (! $classe) {
            return;
        }

        $mere = Tuteur::create(['nom' => 'Petit', 'prenom' => 'Nathalie', 'telephone' => '06 11 22 33 44', 'email' => 'parent@scolaris.test']);
        $mere->user()->associate(User::where('email', 'parent@scolaris.test')->firstOrFail())->save();

        $lucas = $eleves->creer(
            ['nom' => 'Petit', 'prenom' => 'Lucas', 'sexe' => 'M', 'date_naissance' => ($classe->anneeScolaire->date_debut->year - 11).'-05-17', 'lieu_naissance' => 'Bordeaux'],
            [['tuteur_id' => $mere->id, 'lien' => 'mere']],
        );
        $lucas->user()->associate(User::where('email', 'eleve@scolaris.test')->firstOrFail())->save();

        // La capacité (32) laisse de la place après les 24 élèves générés
        $inscriptions->inscrire($lucas, $classe, $classe->anneeScolaire->date_debut->toDateString());
    }
}
