<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // La classe se déduit de l'affectation
        Schema::create('creneaux', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affectation_id')->constrained('affectations')->cascadeOnDelete();
            $table->unsignedTinyInteger('jour'); // 1 = lundi … 6 = samedi
            $table->time('heure_debut');
            $table->time('heure_fin');
            $table->string('salle', 50)->nullable();
            $table->timestamps();

            $table->index(['jour', 'heure_debut']);
        });

        Schema::create('absences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eleve_id')->constrained('eleves')->restrictOnDelete();
            $table->foreignId('creneau_id')->nullable()->constrained('creneaux')->nullOnDelete();
            $table->date('date');
            $table->time('heure_debut');
            $table->time('heure_fin');
            $table->string('type', 20); // absence | retard
            $table->boolean('justifiee')->default(false);
            $table->string('motif')->nullable();
            $table->foreignId('saisie_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Un même appel ne peut pas enregistrer deux fois le même élève
            $table->unique(['eleve_id', 'creneau_id', 'date']);
            $table->index(['eleve_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absences');
        Schema::dropIfExists('creneaux');
    }
};
