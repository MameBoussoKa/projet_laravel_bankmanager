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
                'statut' => $compte->statut,
                'motifBlocage' => $compte->motifBlocage,
                'dateBlocage' => $compte->dateBlocage?->toISOString(),
                'dateDeblocagePrevue' => $compte->dateDeblocagePrevue?->toISOString(),
                'client_id' => $compte->client_id,
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

    /**
     * Archive account transactions to cloud storage
     */
    public function archiveAccountTransactions(Compte $compte): bool
    {
        try {
            $transactions = $compte->transactions->map(function ($transaction) {
                return [
                    'type' => $transaction->type,
                    'montant' => $transaction->montant,
                    'date' => $transaction->date->toISOString(),
                    'statut' => $transaction->statut,
                    'compte_id' => $transaction->compte_id,
                    'metadata' => $transaction->metadata ?? [],
                ];
            })->toArray();

            $data = [
                'compte_id' => $compte->id,
                'numero_compte' => $compte->numero_compte,
                'transactions' => $transactions,
                'date_archivage' => now()->toISOString(),
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/archive-account-transactions', $data);

            if ($response->successful()) {
                Log::info('Account transactions successfully archived to cloud', [
                    'compte_id' => $compte->id,
                    'numero_compte' => $compte->numero_compte,
                    'transaction_count' => count($transactions)
                ]);
                return true;
            }

            Log::error('Failed to archive account transactions to cloud', [
                'compte_id' => $compte->id,
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('Exception while archiving account transactions to cloud', [
                'compte_id' => $compte->id,
                'message' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Get archived blocked accounts from cloud storage
     */
    public function getArchivedBlockedAccounts(): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Accept' => 'application/json',
            ])->get($this->baseUrl . '/archived-accounts/blocked');

            if ($response->successful()) {
                return $response->json()['data'] ?? [];
            }

            Log::error('Failed to retrieve archived blocked accounts from cloud', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return [];
        } catch (\Exception $e) {
            Log::error('Exception while retrieving archived blocked accounts from cloud', [
                'message' => $e->getMessage()
            ]);

            return [];
        }
    }

    /**
     * Restore account transactions from cloud storage
     */
    public function restoreAccountTransactions(Compte $compte, string $archivedAccountId): bool
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Accept' => 'application/json',
            ])->get($this->baseUrl . "/archived-accounts/{$archivedAccountId}/transactions");

            if ($response->successful()) {
                $transactions = $response->json()['data'] ?? [];

                foreach ($transactions as $transactionData) {
                    \App\Models\Transaction::create([
                        'type' => $transactionData['type'],
                        'montant' => $transactionData['montant'],
                        'date' => $transactionData['date'],
                        'statut' => $transactionData['statut'],
                        'compte_id' => $compte->id,
                    ]);
                }

                Log::info('Account transactions successfully restored from cloud', [
                    'compte_id' => $compte->id,
                    'numero_compte' => $compte->numero_compte,
                    'transaction_count' => count($transactions)
                ]);

                return true;
            }

            Log::error('Failed to restore account transactions from cloud', [
                'compte_id' => $compte->id,
                'archived_account_id' => $archivedAccountId,
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('Exception while restoring account transactions from cloud', [
                'compte_id' => $compte->id,
                'archived_account_id' => $archivedAccountId,
                'message' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Remove account from cloud archive
     */
    public function removeFromArchive(string $archivedAccountId): bool
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
            ])->delete($this->baseUrl . "/archived-accounts/{$archivedAccountId}");

            if ($response->successful()) {
                Log::info('Account successfully removed from cloud archive', [
                    'archived_account_id' => $archivedAccountId
                ]);
                return true;
            }

            Log::error('Failed to remove account from cloud archive', [
                'archived_account_id' => $archivedAccountId,
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('Exception while removing account from cloud archive', [
                'archived_account_id' => $archivedAccountId,
                'message' => $e->getMessage()
            ]);

            return false;
        }
    }
}