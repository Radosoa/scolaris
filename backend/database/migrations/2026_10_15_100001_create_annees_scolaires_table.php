<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('annees_scolaires', function (Blueprint $table) {
            $table->id();
            $table->string('libelle', 20)->unique();
            $table->date('date_debut');
            $table->date('date_fin');
            // Une seule année active : garanti par AnneeScolaireService (transaction)
            $table->boolean('active')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('periodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('annee_scolaire_id')->constrained('annees_scolaires')->cascadeOnDelete();
            $table->string('libelle', 50);
            $table->unsignedTinyInteger('ordre');
            $table->date('date_debut');
            $table->date('date_fin');
            $table->boolean('cloturee')->default(false);
            $table->timestamps();

            $table->unique(['annee_scolaire_id', 'ordre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('periodes');
        Schema::dropIfExists('annees_scolaires');
    }
};
