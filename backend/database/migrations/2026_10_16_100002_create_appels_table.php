<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Trace de l'appel d'un créneau à une date : sans elle, un appel où tous les élèves
     * sont présents ne laisse aucune ligne et ne se distingue pas d'un appel non fait.
     */
    public function up(): void
    {
        Schema::create('appels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creneau_id')->constrained('creneaux')->cascadeOnDelete();
            $table->date('date');
            $table->foreignId('fait_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['creneau_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appels');
    }
};
