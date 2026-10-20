<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Date de fin de création de l'école (base, tables, données). Vide = création interrompue
     * (serveur arrêté pendant le remplissage) : l'école de démonstration est alors recréée.
     */
    public function up(): void
    {
        Schema::table('ecoles', function (Blueprint $table) {
            $table->timestamp('prete_le')->nullable()->after('actif');
        });
    }

    public function down(): void
    {
        Schema::table('ecoles', function (Blueprint $table) {
            $table->dropColumn('prete_le');
        });
    }
};
