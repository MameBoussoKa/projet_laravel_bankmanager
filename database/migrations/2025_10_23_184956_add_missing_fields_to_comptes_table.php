<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('comptes', function (Blueprint $table) {
            $table->string('devise')->default('FCFA')->after('solde');
            $table->text('motifBlocage')->nullable()->after('statut');
            $table->json('metadata')->nullable()->after('motifBlocage');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('comptes', function (Blueprint $table) {
            $table->dropColumn(['devise', 'motifBlocage', 'metadata']);
        });
    }
};
