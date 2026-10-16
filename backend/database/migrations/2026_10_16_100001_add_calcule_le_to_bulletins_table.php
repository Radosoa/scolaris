<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Date du dernier calcul, distincte de updated_at (modifiée aussi par les appréciations). */
    public function up(): void
    {
        Schema::table('bulletins', function (Blueprint $table) {
            $table->timestamp('calcule_le')->nullable()->after('nb_retards');
        });
    }

    public function down(): void
    {
        Schema::table('bulletins', function (Blueprint $table) {
            $table->dropColumn('calcule_le');
        });
    }
};
