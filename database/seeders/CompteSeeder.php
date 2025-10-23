<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CompteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $clients = \App\Models\Client::all();
        foreach ($clients as $client) {
            \App\Models\Compte::factory(rand(1, 3))->create(['client_id' => $client->id]);
        }
    }
}
