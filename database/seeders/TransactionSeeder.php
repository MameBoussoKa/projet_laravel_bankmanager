<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TransactionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $comptes = \App\Models\Compte::all();
        foreach ($comptes as $compte) {
            \App\Models\Transaction::factory(rand(5, 10))->create(['compte_id' => $compte->id]);
        }
    }
}
