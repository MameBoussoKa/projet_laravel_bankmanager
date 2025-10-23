<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\User::factory(10)->create();

        // Create a test admin user
        \App\Models\User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@bankmanager.com',
        ]);

        // Create a test regular user
        \App\Models\User::factory()->create([
            'name' => 'Test User',
            'email' => 'user@bankmanager.com',
        ]);
    }
}
