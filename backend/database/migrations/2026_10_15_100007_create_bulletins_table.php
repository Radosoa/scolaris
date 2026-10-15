<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Moyennes stockées avec 4 décimales : l'arrondi à 2 décimales se fait
        // à l'affichage, les rangs sont calculés sur les valeurs exactes.
        Schema::create('bulletins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inscription_id')->constrained('inscriptions')->restrictOnDelete();
            $table->foreignId('periode_id')->constrained('periodes')->restrictOnDelete();
            $table->decimal('moyenne', 7, 4)->nullable();
            $table->unsignedSmallInteger('rang')->nullable();
            $table->unsignedSmallInteger('effectif');
            $table->string('mention', 60)->nullable();
            $table->text('appreciation_generale')->nullable();
            $table->decimal('moyenne_classe', 7, 4)->nullable();
            $table->decimal('moyenne_max', 7, 4)->nullable();
            $table->decimal('moyenne_min', 7, 4)->nullable();
            $table->decimal('heures_abs_justifiees', 6, 2)->default(0);
            $table->decimal('heures_abs_non_justifiees', 6, 2)->default(0);
            $table->unsignedSmallInteger('nb_retards')->default(0);
            $table->boolean('valide')->default(false);
            $table->foreignId('valide_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('valide_le')->nullable();
            $table->string('pdf_path')->nullable();
            $table->timestamps();

            $table->unique(['inscription_id', 'periode_id']);
            $table->index(['periode_id', 'valide']);
        });

        Schema::create('bulletin_lignes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bulletin_id')->constrained('bulletins')->cascadeOnDelete();
            $table->foreignId('matiere_id')->constrained('matieres')->restrictOnDelete();
            $table->decimal('moyenne', 7, 4)->nullable();
            $table->decimal('coefficient', 4, 2);
            $table->unsignedSmallInteger('rang_matiere')->nullable();
            $table->decimal('moyenne_classe', 7, 4)->nullable();
            $table->decimal('moyenne_max', 7, 4)->nullable();
            $table->decimal('moyenne_min', 7, 4)->nullable();
            $table->text('appreciation')->nullable();
            $table->timestamps();

            $table->unique(['bulletin_id', 'matiere_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulletin_lignes');
        Schema::dropIfExists('bulletins');
    }
};
