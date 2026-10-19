<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Base centrale : les écoles clientes et leur base de données. L'identité complète
     * (adresse, téléphone…) est dans la base de l'école (table parametres).
     */
    public function up(): void
    {
        Schema::create('ecoles', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('base_de_donnees', 64)->unique();
            $table->string('nom');
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecoles');
    }
};
