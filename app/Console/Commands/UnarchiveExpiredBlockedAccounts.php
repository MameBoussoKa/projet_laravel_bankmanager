<?php

namespace App\Console\Commands;

use App\Models\Compte;
use App\Services\CloudStorageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UnarchiveExpiredBlockedAccounts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:unarchive-expired-blocked-accounts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Unarchive accounts where the blocking period has expired and restore them locally';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting unarchive process for expired blocked accounts...');

        $cloudService = new CloudStorageService();

        // Get all archived blocked accounts from cloud
        $archivedAccounts = $cloudService->getArchivedBlockedAccounts();

        if (empty($archivedAccounts)) {
            $this->info('No archived blocked accounts found.');
            return;
        }

        $this->info("Found " . count($archivedAccounts) . " archived blocked accounts to check.");

        $unarchivedCount = 0;
        $skippedCount = 0;

        foreach ($archivedAccounts as $archivedAccount) {
            try {
                $this->line("Processing archived account: {$archivedAccount['numero_compte']}");

                // Check if the blocking period has expired
                $dateDeblocagePrevue = isset($archivedAccount['dateDeblocagePrevue'])
                    ? \Carbon\Carbon::parse($archivedAccount['dateDeblocagePrevue'])
                    : null;

                if (!$dateDeblocagePrevue || $dateDeblocagePrevue->isFuture()) {
                    $this->line("Account {$archivedAccount['numero_compte']} blocking period not yet expired, skipping.");
                    $skippedCount++;
                    continue;
                }

                // Restore account locally
                $compte = Compte::withTrashed()->where('numero_compte', $archivedAccount['numero_compte'])->first();

                if ($compte) {
                    // If account exists (soft deleted), restore it
                    $compte->restore();
                    $compte->update([
                        'statut' => 'actif',
                        'motifBlocage' => null,
                        'dateBlocage' => null,
                        'dateDeblocagePrevue' => null,
                    ]);
                } else {
                    // Create new account record
                    $compte = Compte::create([
                        'numero_compte' => $archivedAccount['numero_compte'],
                        'type' => $archivedAccount['type'],
                        'statut' => 'actif',
                        'client_id' => $archivedAccount['client_id'],
                        'devise' => $archivedAccount['devise'] ?? 'FCFA',
                        'motifBlocage' => null,
                        'dateBlocage' => null,
                        'dateDeblocagePrevue' => null,
                        'metadata' => $archivedAccount['metadata'] ?? [],
                    ]);
                }

                // Restore transactions
                $cloudService->restoreAccountTransactions($compte, $archivedAccount['id']);

                // Remove from cloud archive
                $cloudService->removeFromArchive($archivedAccount['id']);

                $unarchivedCount++;
                $this->info("Successfully unarchived account: {$archivedAccount['numero_compte']}");

            } catch (\Exception $e) {
                $this->error("Error unarchiving account {$archivedAccount['numero_compte']}: " . $e->getMessage());
                Log::error('UnarchiveExpiredBlockedAccounts command error', [
                    'numero_compte' => $archivedAccount['numero_compte'],
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->info("Unarchive process completed. Unarchived: {$unarchivedCount}, Skipped: {$skippedCount}");
    }
}
