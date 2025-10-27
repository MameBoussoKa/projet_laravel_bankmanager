<?php

namespace App\Services;

use App\Models\Compte;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CloudStorageService
{
    protected string $baseUrl;
    protected string $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('services.cloud_storage.url', 'https://api.cloud-storage.example.com/v1');
        $this->apiKey = config('services.cloud_storage.api_key', 'default-api-key');
    }

    /**
     * Retrieve archived savings accounts from cloud storage
     */
    public function getArchivedSavingsAccounts(array $filters = []): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Accept' => 'application/json',
            ])->get($this->baseUrl . '/archived-accounts/savings', $filters);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Failed to retrieve archived accounts from cloud', [
                'status' => $response->status(),
                'response' => $response->body(),
                'filters' => $filters
            ]);

            return [];
        } catch (\Exception $e) {
            Log::error('Exception while retrieving archived accounts from cloud', [
                'message' => $e->getMessage(),
                'filters' => $filters
            ]);

            return [];
        }
    }

    /**
     * Retrieve a specific archived account from cloud storage
     */
    public function getArchivedAccount(string $compteId): ?array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Accept' => 'application/json',
            ])->get($this->baseUrl . '/archived-accounts/' . $compteId);

            if ($response->successful()) {
                return $response->json();
            }

            if ($response->status() === 404) {
                return null;
            }

            Log::error('Failed to retrieve archived account from cloud', [
                'compte_id' => $compteId,
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Exception while retrieving archived account from cloud', [
                'compte_id' => $compteId,
                'message' => $e->getMessage()
            ]);

            return null;
        }
    }

    /**
     * Store account data to cloud archive
     */
    public function archiveAccount(Compte $compte): bool
    {
        try {
            $data = [
                'numero_compte' => $compte->numero_compte,
                'titulaire' => $compte->titulaire,
                'type' => $compte->type,
                'solde' => $compte->solde,
                'devise' => $compte->devise,
                'date_creation' => $compte->date_creation,
                'date_archivage' => now()->toISOString(),
                'metadata' => $compte->metadata,
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/archive-account', $data);

            if ($response->successful()) {
                Log::info('Account successfully archived to cloud', [
                    'compte_id' => $compte->id,
                    'numero_compte' => $compte->numero_compte
                ]);
                return true;
            }

            Log::error('Failed to archive account to cloud', [
                'compte_id' => $compte->id,
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('Exception while archiving account to cloud', [
                'compte_id' => $compte->id,
                'message' => $e->getMessage()
            ]);

            return false;
        }
    }
}