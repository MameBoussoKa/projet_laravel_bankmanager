<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Compte;
use App\Models\Client;
use Illuminate\Http\Request;

class CompteController extends Controller
{
    public function index()
    {
        $comptes = Compte::with('client')->get();
        return response()->json($comptes, 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'numero_compte' => 'required|string|max:255|unique:comptes,numero_compte',
            'type' => 'required|string|max:255',
            'solde' => 'required|numeric|min:0',
            'statut' => 'required|string|max:255',
            'client_id' => 'required|exists:clients,id',
        ]);

        $compte = Compte::create($validated);
        return response()->json($compte->load('client'), 201);
    }

    public function show(Compte $compte)
    {
        return response()->json($compte->load('client'), 200);
    }

    public function update(Request $request, Compte $compte)
    {
        $validated = $request->validate([
            'numero_compte' => 'sometimes|required|string|max:255|unique:comptes,numero_compte,' . $compte->id,
            'type' => 'sometimes|required|string|max:255',
            'solde' => 'sometimes|required|numeric|min:0',
            'statut' => 'sometimes|required|string|max:255',
            'client_id' => 'sometimes|required|exists:clients,id',
        ]);

        $compte->update($validated);
        return response()->json($compte->load('client'), 200);
    }

    public function destroy(Compte $compte)
    {
        $compte->delete();
        return response()->json(null, 204);
    }
}