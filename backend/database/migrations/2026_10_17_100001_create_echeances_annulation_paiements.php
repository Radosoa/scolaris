<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Échéancier de l'année : part cumulée des frais exigible à chaque date limite
        Schema::create('echeances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('annee_scolaire_id')->constrained('annees_scolaires')->cascadeOnDelete();
            $table->string('libelle', 60);
            $table->date('date_limite');
            $table->decimal('pourcentage', 5, 2);
            $table->timestamps();

            $table->unique(['annee_scolaire_id', 'date_limite']);
        });

        // Un paiement ne se supprime pas : il s'annule (traçabilité des reçus)
        Schema::table('paiements', function (Blueprint $table) {
            $table->timestamp('annule_le')->nullable()->after('encaisse_par');
            $table->foreignId('annule_par')->nullable()->after('annule_le')->constrained('users')->nullOnDelete();
            $table->string('motif_annulation')->nullable()->after('annule_par');
        });
    }

    public function down(): void
    {
        Schema::table('paiements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('annule_par');
            $table->dropColumn(['annule_le', 'motif_annulation']);
        });
        Schema::dropIfExists('echeances');
    }
};
