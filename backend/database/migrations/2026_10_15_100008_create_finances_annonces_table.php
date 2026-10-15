<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('frais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('niveau_id')->constrained('niveaux')->restrictOnDelete();
            $table->foreignId('annee_scolaire_id')->constrained('annees_scolaires')->restrictOnDelete();
            $table->string('libelle', 100);
            $table->decimal('montant', 12, 2);
            $table->timestamps();

            $table->unique(['niveau_id', 'annee_scolaire_id', 'libelle']);
        });

        Schema::create('paiements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inscription_id')->constrained('inscriptions')->restrictOnDelete();
            $table->string('numero_recu', 30)->unique();
            $table->decimal('montant', 12, 2);
            $table->date('date');
            $table->string('mode', 20); // especes | cheque | virement | mobile_money
            $table->string('reference', 100)->nullable();
            $table->foreignId('encaisse_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('date');
        });

        Schema::create('annonces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auteur_id')->constrained('users')->restrictOnDelete();
            $table->string('titre', 150);
            $table->text('contenu');
            $table->string('cible', 20); // tous | professeurs | parents | classe
            $table->foreignId('classe_id')->nullable()->constrained('classes')->cascadeOnDelete();
            $table->dateTime('date_publication')->index();
            $table->timestamps();

            $table->index(['cible', 'classe_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('annonces');
        Schema::dropIfExists('paiements');
        Schema::dropIfExists('frais');
    }
};
