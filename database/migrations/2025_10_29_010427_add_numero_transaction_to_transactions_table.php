<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('numero_transaction')->nullable()->after('id');
        });

        // Generate numero_transaction for existing records
        $transactions = DB::table('transactions')->whereNull('numero_transaction')->get();
        foreach ($transactions as $transaction) {
            DB::table('transactions')
                ->where('id', $transaction->id)
                ->update(['numero_transaction' => 'TXN' . str_pad($transaction->id, 10, '0', STR_PAD_LEFT)]);
        }

        // Make it not null and unique
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('numero_transaction')->nullable(false)->change();
            $table->unique('numero_transaction');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('numero_transaction');
        });
    }
};
