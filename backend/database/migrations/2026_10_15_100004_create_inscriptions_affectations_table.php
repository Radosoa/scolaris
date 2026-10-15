<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eleve_id')->constrained('eleves')->restrictOnDelete();
            $table->foreignId('classe_id')->constrained('classes')->restrictOnDelete();
            // Redondant avec classes.annee_scolaire_id mais nécessaire à l'unicité
            // (une inscription par élève et par année) ; cohérence vérifiée par le service.
            $table->foreignId('annee_scolaire_id')->constrained('annees_scolaires')->restrictOnDelete();
            $table->string('statut', 20)->default('inscrit'); // inscrit | transfere | abandon
            $table->date('date_inscription');
            $table->timestamps();

            $table->unique(['eleve_id', 'annee_scolaire_id']);
            $table->index(['classe_id', 'statut']);
        });

        // L'année se déduit de la classe : un seul enseignant par matière et par classe
        Schema::create('affectations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enseignant_id')->constrained('enseignants')->restrictOnDelete();
            $table->foreignId('matiere_id')->constrained('matieres')->restrictOnDelete();
            $table->foreignId('classe_id')->constrained('classes')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['classe_id', 'matiere_id']);
            $table->index('enseignant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affectations');
        Schema::dropIfExists('inscriptions');
    }
};
