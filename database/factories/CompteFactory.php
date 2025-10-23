<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Compte>
 */
class CompteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'numero_compte' => $this->faker->unique()->numerify('############'),
            'type' => $this->faker->randomElement(['courant', 'epargne']),
            'solde' => $this->faker->randomFloat(2, 0, 10000),
            'statut' => $this->faker->randomElement(['actif', 'bloque', 'ferme']),
            'client_id' => \App\Models\Client::factory(),
        ];
    }
}
