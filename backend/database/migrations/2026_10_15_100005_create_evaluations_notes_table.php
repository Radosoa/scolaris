<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affectation_id')->constrained('affectations')->restrictOnDelete();
            $table->foreignId('periode_id')->constrained('periodes')->restrictOnDelete();
            $table->string('type', 20); // devoir | interrogation | composition
            $table->string('titre', 150);
            $table->date('date');
            $table->decimal('bareme', 5, 2)->default(20);
            $table->timestamps();

            $table->index(['affectation_id', 'periode_id']);
        });

        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_id')->constrained('evaluations')->cascadeOnDelete();
            $table->foreignId('eleve_id')->constrained('eleves')->restrictOnDelete();
            $table->decimal('valeur', 5, 2)->nullable();
            $table->boolean('absent')->default(false);
            $table->timestamps();

            $table->unique(['evaluation_id', 'eleve_id']);
            $table->index('eleve_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notes');
        Schema::dropIfExists('evaluations');
    }
};
