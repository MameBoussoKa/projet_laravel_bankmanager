<?php

namespace App\Console\Commands;

use App\Models\Compte;
use App\Services\CloudStorageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ArchiveExpiredBlockedAccounts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:archive-expired-blocked-accounts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Archive accounts where the blocking period has expired';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting archive process for expired blocked accounts...');

        // Find all blocked accounts where dateDeblocagePrevue is in the past
        $expiredBlockedAccounts = Compte::withoutGlobalScopes()
            ->where('statut', 'bloque')
            ->where('dateDeblocagePrevue', '<=', now())
            ->with(['client', 'transactions'])
            ->get();

        if ($expiredBlockedAccounts->isEmpty()) {
            $this->info('No expired blocked accounts found.');
            return;
        }

        $this->info("Found {$expiredBlockedAccounts->count()} expired blocked accounts to archive.");

        $cloudService = new CloudStorageService();
        $archivedCount = 0;
        $failedCount = 0;

        foreach ($expiredBlockedAccounts as $compte) {
            try {
                $this->line("Processing account: {$compte->numero_compte}");

                // Archive account to cloud
                $accountArchived = $cloudService->archiveAccount($compte);

                if (!$accountArchived) {
                    $this->error("Failed to archive account {$compte->numero_compte} to cloud");
                    $failedCount++;
                    continue;
                }

                // Archive transactions to cloud
                $transactionsArchived = $cloudService->archiveAccountTransactions($compte);

                if (!$transactionsArchived) {
                    $this->warn("Account {$compte->numero_compte} archived but transactions archiving failed");
                }

                // Soft delete the account from local database
                $compte->delete();

                $archivedCount++;
                $this->info("Successfully archived account: {$compte->numero_compte}");

            } catch (\Exception $e) {
                $this->error("Error archiving account {$compte->numero_compte}: " . $e->getMessage());
                Log::error('ArchiveExpiredBlockedAccounts command error', [
                    'compte_id' => $compte->id,
                    'numero_compte' => $compte->numero_compte,
                    'error' => $e->getMessage()
                ]);
                $failedCount++;
            }
        }

        $this->info("Archive process completed. Archived: {$archivedCount}, Failed: {$failedCount}");
    }
}
