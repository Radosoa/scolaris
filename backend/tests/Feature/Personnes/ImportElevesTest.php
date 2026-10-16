<?php

namespace Tests\Feature\Personnes;

use App\Enums\Role;
use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\Inscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Shared\Date as DateExcel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ImportElevesTest extends TestCase
{
    use RefreshDatabase;

    private Classe $sixiemeA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connecter(Role::Secretariat);
        $annee = AnneeScolaire::factory()->debutant(2026)->active()->create();
        $this->sixiemeA = Classe::factory()->for($annee)->create(['nom' => '6e A', 'capacite' => 30]);
    }

    /** @param list<list<string>> $lignes */
    private function csv(array $lignes, string $entete = 'nom,prenom,sexe,date_naissance,lieu_naissance,classe'): UploadedFile
    {
        $contenu = $entete."\n".implode("\n", array_map(fn ($l) => implode(',', $l), $lignes))."\n";

        return UploadedFile::fake()->createWithContent('eleves.csv', $contenu);
    }

    private function fichierMixte(): UploadedFile
    {
        Eleve::factory()->create(['nom' => 'Martin', 'prenom' => 'Hugo', 'date_naissance' => '2013-11-02', 'matricule' => '2026-0099']);

        return $this->csv([
            ['Dupont', 'Camille', 'F', '14/03/2014', 'Lyon', '6e A'],      // ligne 2 : valide + inscription
            ['Bernard', 'Lucas', 'M', '2013-05-20', '', ''],               // ligne 3 : valide, sans classe
            ['Leroy', 'Emma', 'X', '01/01/2014', '', ''],                  // ligne 4 : sexe invalide
            ['Moreau', 'Louis', 'M', '31/02/2014', '', ''],                // ligne 5 : date impossible
            ['Simon', 'Jade', 'F', '10/10/2013', '', '5e Z'],              // ligne 6 : classe inconnue
            ['Dupont', 'Camille', 'F', '14/03/2014', '', ''],              // ligne 7 : doublon de la ligne 2
            ['Martin', 'Hugo', 'M', '02/11/2013', '', ''],                 // ligne 8 : déjà en base
            ['', 'Sans nom', 'F', '01/01/2014', '', ''],                   // ligne 9 : nom manquant
        ]);
    }

    public function test_l_analyse_rapporte_les_erreurs_ligne_par_ligne_sans_rien_enregistrer(): void
    {
        $rapport = $this->postJson('/api/eleves/import/analyse', ['fichier' => $this->fichierMixte()])
            ->assertOk()
            ->assertJsonPath('data.total', 8)
            ->assertJsonPath('data.valides', 2)
            ->json('data.lignes');

        $erreurs = collect($rapport)->mapWithKeys(fn ($l) => [$l['ligne'] => $l['erreurs']]);

        $this->assertSame([], $erreurs[2]);
        $this->assertSame([], $erreurs[3]);
        $this->assertSame(['Sexe invalide : indiquez M ou F.'], $erreurs[4]);
        $this->assertStringContainsString('Date de naissance', $erreurs[5][0]);
        $this->assertSame(['Classe « 5e Z » introuvable pour l\'année active.'], $erreurs[6]);
        $this->assertSame(['Doublon de la ligne 2 dans le fichier.'], $erreurs[7]);
        $this->assertSame(['Élève déjà enregistré (matricule 2026-0099).'], $erreurs[8]);
        $this->assertStringContainsString('nom', $erreurs[9][0]);

        $this->assertSame(1, Eleve::count()); // seul l'élève préexistant
    }

    public function test_l_import_enregistre_les_lignes_valides_et_les_inscrit(): void
    {
        $this->postJson('/api/eleves/import', ['fichier' => $this->fichierMixte()])
            ->assertOk()
            ->assertJsonPath('data.importes', 2)
            ->assertJsonCount(6, 'data.erreurs')
            ->assertJsonPath('data.erreurs.0.ligne', 4);

        $camille = Eleve::where('prenom', 'Camille')->firstOrFail();
        $this->assertSame('2014-03-14', $camille->date_naissance->toDateString());
        $this->assertSame('2026-0100', $camille->matricule);
        $this->assertTrue(Inscription::where('eleve_id', $camille->id)->where('classe_id', $this->sixiemeA->id)->exists());
        $this->assertFalse(Inscription::whereHas('eleve', fn ($q) => $q->where('prenom', 'Lucas'))->exists());
    }

    public function test_la_capacite_de_la_classe_est_controlee_pendant_l_import(): void
    {
        $this->sixiemeA->update(['capacite' => 1]);

        $rapport = $this->postJson('/api/eleves/import/analyse', ['fichier' => $this->csv([
            ['Dupont', 'Camille', 'F', '14/03/2014', '', '6e A'],
            ['Petit', 'Léo', 'M', '15/03/2014', '', '6e a'],
        ])])->assertOk()->json('data');

        $this->assertSame(1, $rapport['valides']);
        $this->assertSame(['La classe 6e a est complète.'], $rapport['lignes'][1]['erreurs']);
    }

    public function test_un_fichier_xlsx_avec_des_dates_excel_est_accepte(): void
    {
        $classeur = new Spreadsheet;
        $classeur->getActiveSheet()->fromArray([
            ['Nom', 'Prénom', 'Sexe', 'Date de naissance', 'Lieu de naissance', 'Classe'],
            ['Girard', 'Inès', 'Féminin', DateExcel::PHPToExcel(new \DateTime('2014-06-30')), 'Lille', '6e A'],
        ]);
        $chemin = tempnam(sys_get_temp_dir(), 'import').'.xlsx';
        (new Xlsx($classeur))->save($chemin);

        $this->postJson('/api/eleves/import', ['fichier' => new UploadedFile($chemin, 'eleves.xlsx', null, null, true)])
            ->assertOk()
            ->assertJsonPath('data.importes', 1);

        $ines = Eleve::where('prenom', 'Inès')->firstOrFail();
        $this->assertSame('2014-06-30', $ines->date_naissance->toDateString());
        $this->assertSame('F', $ines->sexe->value);
    }

    public function test_les_colonnes_obligatoires_sont_exigees(): void
    {
        $this->postJson('/api/eleves/import/analyse', ['fichier' => $this->csv([['Dupont', 'Camille']], 'nom,prenom')])
            ->assertUnprocessable()
            ->assertJsonPath('errors.fichier.0', 'Colonnes manquantes : sexe, date_naissance. Utilisez le modèle fourni.');
    }

    public function test_un_fichier_vide_ou_d_un_mauvais_type_est_refuse(): void
    {
        $this->postJson('/api/eleves/import/analyse', ['fichier' => $this->csv([])])
            ->assertUnprocessable()
            ->assertJsonPath('errors.fichier.0', 'Le fichier ne contient aucun élève.');

        $this->postJson('/api/eleves/import/analyse', ['fichier' => UploadedFile::fake()->create('eleves.pdf', 10, 'application/pdf')])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('fichier');
    }

    public function test_le_modele_est_telechargeable(): void
    {
        $this->get('/api/eleves/import/modele')
            ->assertOk()
            ->assertDownload('modele-import-eleves.xlsx');
    }

    public function test_le_professeur_ne_peut_pas_importer(): void
    {
        $this->connecter(Role::Professeur);

        $this->postJson('/api/eleves/import', ['fichier' => $this->csv([])])->assertForbidden();
    }
}
