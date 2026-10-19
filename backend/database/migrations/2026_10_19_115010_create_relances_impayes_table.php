<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Trace des avertissements de retard envoyés : une famille n'est avertie
     * qu'une fois par tranche (mois) impayée, même si la détection tourne chaque jour.
     */
    public function up(): void
    {
        Schema::create('relances_impayes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('echeance_id')->constrained()->cascadeOnDelete();
            $table->decimal('montant_retard', 12, 2);
            $table->timestamps();

            $table->unique(['inscription_id', 'echeance_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('relances_impayes');
    }
};
