<?php

namespace App\Observers;

use App\Models\Transaction;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TransactionObserver
{
    /**
     * Handle the Transaction "creating" event.
     */
    public function creating(Transaction $transaction): void
    {
        // Generate unique transaction number if not provided
        if (empty($transaction->numero_transaction)) {
            $transaction->numero_transaction = 'TXN' . strtoupper(Str::random(10));
        }
    }

    /**
     * Handle the Transaction "created" event.
     */
    public function created(Transaction $transaction): void
    {
        // Log transaction creation for audit
        Log::info('Transaction created', [
            'id' => $transaction->id,
            'numero_transaction' => $transaction->numero_transaction,
            'type' => $transaction->type,
            'montant' => $transaction->montant,
            'compte_id' => $transaction->compte_id,
        ]);
    }

    /**
     * Handle the Transaction "updating" event.
     */
    public function updating(Transaction $transaction): void
    {
        // Prevent modification of completed transactions
        if ($transaction->statut === 'valide' && $transaction->isDirty(['montant', 'type'])) {
            throw new \Exception('Cannot modify completed transactions');
        }
    }
}