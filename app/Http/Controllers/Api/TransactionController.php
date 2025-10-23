<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\Compte;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function index()
    {
        $transactions = Transaction::with('compte')->get();
        return response()->json($transactions, 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|string|max:255',
            'montant' => 'required|numeric|min:0',
            'date' => 'required|date',
            'statut' => 'required|string|max:255',
            'compte_id' => 'required|exists:comptes,id',
        ]);

        $transaction = Transaction::create($validated);

        // Update compte solde based on transaction type
        $compte = Compte::find($validated['compte_id']);
        if ($validated['type'] === 'depot') {
            $compte->solde += $validated['montant'];
        } elseif ($validated['type'] === 'retrait') {
            $compte->solde -= $validated['montant'];
        }
        $compte->save();

        return response()->json($transaction->load('compte'), 201);
    }

    public function show(Transaction $transaction)
    {
        return response()->json($transaction->load('compte'), 200);
    }

    public function update(Request $request, Transaction $transaction)
    {
        $validated = $request->validate([
            'type' => 'sometimes|required|string|max:255',
            'montant' => 'sometimes|required|numeric|min:0',
            'date' => 'sometimes|required|date',
            'statut' => 'sometimes|required|string|max:255',
            'compte_id' => 'sometimes|required|exists:comptes,id',
        ]);

        $oldMontant = $transaction->montant;
        $oldType = $transaction->type;
        $oldCompteId = $transaction->compte_id;

        $transaction->update($validated);

        // Update compte solde if montant or type changed
        if ($validated['compte_id'] !== $oldCompteId || $validated['montant'] !== $oldMontant || $validated['type'] !== $oldType) {
            // Revert old transaction
            $oldCompte = Compte::find($oldCompteId);
            if ($oldType === 'depot') {
                $oldCompte->solde -= $oldMontant;
            } elseif ($oldType === 'retrait') {
                $oldCompte->solde += $oldMontant;
            }
            $oldCompte->save();

            // Apply new transaction
            $newCompte = Compte::find($validated['compte_id']);
            if ($validated['type'] === 'depot') {
                $newCompte->solde += $validated['montant'];
            } elseif ($validated['type'] === 'retrait') {
                $newCompte->solde -= $validated['montant'];
            }
            $newCompte->save();
        }

        return response()->json($transaction->load('compte'), 200);
    }

    public function destroy(Transaction $transaction)
    {
        // Revert solde change
        $compte = $transaction->compte;
        if ($transaction->type === 'depot') {
            $compte->solde -= $transaction->montant;
        } elseif ($transaction->type === 'retrait') {
            $compte->solde += $transaction->montant;
        }
        $compte->save();

        $transaction->delete();
        return response()->json(null, 204);
    }
}