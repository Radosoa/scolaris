<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('niveaux', function (Blueprint $table) {
            $table->id();
            $table->string('libelle', 50)->unique();
            $table->unsignedSmallInteger('ordre')->unique();
            $table->timestamps();
        });

        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('niveau_id')->constrained('niveaux')->restrictOnDelete();
            $table->foreignId('annee_scolaire_id')->constrained('annees_scolaires')->restrictOnDelete();
            $table->string('nom', 50);
            $table->unsignedSmallInteger('capacite');
            $table->timestamps();

            $table->unique(['annee_scolaire_id', 'nom']);
        });

        Schema::create('matieres', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 100);
            $table->string('code', 20)->unique();
            $table->timestamps();
        });

        Schema::create('coefficients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('niveau_id')->constrained('niveaux')->cascadeOnDelete();
            $table->foreignId('matiere_id')->constrained('matieres')->cascadeOnDelete();
            $table->decimal('valeur', 4, 2);
            $table->timestamps();

            $table->unique(['niveau_id', 'matiere_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coefficients');
        Schema::dropIfExists('matieres');
        Schema::dropIfExists('classes');
        Schema::dropIfExists('niveaux');
    }
};
