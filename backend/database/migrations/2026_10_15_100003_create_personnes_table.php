<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eleves', function (Blueprint $table) {
            $table->id();
            $table->string('matricule', 20)->unique();
            $table->string('nom', 100);
            $table->string('prenom', 100);
            $table->char('sexe', 1); // M | F
            $table->date('date_naissance');
            $table->string('lieu_naissance', 100)->nullable();
            $table->string('photo_path')->nullable();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['nom', 'prenom']);
        });

        // « parent » est un mot réservé en PHP : la table s'appelle « tuteurs »
        Schema::create('tuteurs', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 100);
            $table->string('prenom', 100);
            $table->string('telephone', 30);
            $table->string('email')->nullable();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['nom', 'prenom']);
        });

        Schema::create('eleve_tuteur', function (Blueprint $table) {
            $table->foreignId('eleve_id')->constrained('eleves')->cascadeOnDelete();
            $table->foreignId('tuteur_id')->constrained('tuteurs')->cascadeOnDelete();
            $table->string('lien', 30); // pere | mere | tuteur | autre

            $table->primary(['eleve_id', 'tuteur_id']);
        });

        Schema::create('enseignants', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 100);
            $table->string('prenom', 100);
            $table->string('specialite', 100)->nullable();
            $table->string('telephone', 30)->nullable();
            $table->foreignId('user_id')->unique()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['nom', 'prenom']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enseignants');
        Schema::dropIfExists('eleve_tuteur');
        Schema::dropIfExists('tuteurs');
        Schema::dropIfExists('eleves');
    }
};
