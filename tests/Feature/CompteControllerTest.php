<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Client;
use App\Models\Compte;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class CompteControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test deleting a compte as admin (soft delete)
     */
    public function test_admin_can_delete_compte(): void
    {
        // Create admin user
        $admin = Admin::factory()->create();

        // Create a User for authentication (since Admin doesn't implement Authenticatable)
        $user = User::factory()->create([
            'email' => $admin->email
        ]);

        // Create client and compte
        $client = Client::factory()->create();
        $compte = Compte::factory()->create([
            'client_id' => $client->id,
            'statut' => 'actif'
        ]);

        // Authenticate as user (admin)
        $this->actingAs($user, 'sanctum');

        // Delete the compte
        $response = $this->deleteJson("/api/v1/comptes/{$compte->id}");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Compte supprimé avec succès',
                    'data' => [
                        'id' => $compte->id,
                        'numeroCompte' => $compte->numero_compte,
                        'statut' => 'ferme'
                    ]
                ]);

        // Verify compte is soft deleted and status updated
        $this->assertSoftDeleted($compte);
        $this->assertDatabaseHas('comptes', [
            'id' => $compte->id,
            'statut' => 'ferme'
        ]);
    }

    /**
     * Test client cannot delete compte
     */
    public function test_client_cannot_delete_compte(): void
    {
        // Create client user
        $client = Client::factory()->create();

        // Create a User for authentication (since Client doesn't implement Authenticatable)
        $user = User::factory()->create([
            'email' => $client->email
        ]);

        // Create compte for this client
        $compte = Compte::factory()->create([
            'client_id' => $client->id,
            'statut' => 'actif'
        ]);

        // Authenticate as user (client)
        $this->actingAs($user, 'sanctum');

        // Try to delete the compte
        $response = $this->deleteJson("/api/v1/comptes/{$compte->id}");

        $response->assertStatus(403)
                ->assertJson([
                    'success' => false,
                    'error' => [
                        'code' => 'FORBIDDEN',
                        'message' => 'Seuls les administrateurs peuvent supprimer des comptes'
                    ]
                ]);

        // Verify compte is not deleted
        $this->assertDatabaseHas('comptes', [
            'id' => $compte->id,
            'statut' => 'actif',
            'deleted_at' => null
        ]);
    }

    /**
     * Test deleting already closed compte
     */
    public function test_cannot_delete_already_closed_compte(): void
    {
        // Create admin user
        $admin = Admin::factory()->create();

        // Create a User for authentication (since Admin doesn't implement Authenticatable)
        $user = User::factory()->create([
            'email' => $admin->email
        ]);

        // Create client and already closed compte
        $client = Client::factory()->create();
        $compte = Compte::factory()->create([
            'client_id' => $client->id,
            'statut' => 'ferme'
        ]);

        // Authenticate as user (admin)
        $this->actingAs($user, 'sanctum');

        // Try to delete the already closed compte
        $response = $this->deleteJson("/api/v1/comptes/{$compte->id}");

        $response->assertStatus(400)
                ->assertJson([
                    'success' => false,
                    'error' => [
                        'code' => 'ACCOUNT_ALREADY_CLOSED',
                        'message' => 'Le compte est déjà fermé'
                    ]
                ]);
    }

    /**
     * Test deleting non-existent compte
     */
    public function test_delete_non_existent_compte(): void
    {
        // Create admin user
        $admin = Admin::factory()->create();

        // Create a User for authentication (since Admin doesn't implement Authenticatable)
        $user = User::factory()->create([
            'email' => $admin->email
        ]);

        // Authenticate as user (admin)
        $this->actingAs($user, 'sanctum');

        // Try to delete non-existent compte
        $response = $this->deleteJson("/api/v1/comptes/999");

        $response->assertStatus(404);
    }
}
