<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Compte;
use App\Models\Client;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CompteController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        // Determine if user is admin or client
        $isAdmin = Admin::where('email', $user->email)->exists();
        $isClient = Client::where('email', $user->email)->exists();

        $query = Compte::with('client');

        // If user is client, only show their accounts
        if ($isClient) {
            $client = Client::where('email', $user->email)->first();
            if ($client) {
                $query->where('client_id', $client->id);
            }
        }
        // Admin can see all accounts (no additional filter needed)

        // Apply filters
        if ($request->has('type') && $request->type) {
            $query->where('type', $request->type);
        }

        if ($request->has('statut') && $request->statut) {
            $query->where('statut', $request->statut);
        }

        // Search functionality
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('numero_compte', 'like', "%{$search}%")
                  ->orWhereHas('client', function ($clientQuery) use ($search) {
                      $clientQuery->where('nom', 'like', "%{$search}%")
                                  ->orWhere('prenom', 'like', "%{$search}%")
                                  ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        // Sorting
        $sortBy = $request->get('sort', 'created_at');
        $sortOrder = $request->get('order', 'desc');

        // Map sort fields to actual database columns
        $sortFields = [
            'dateCreation' => 'created_at',
            'solde' => 'solde',
            'titulaire' => 'clients.nom', // This will need special handling
        ];

        if (array_key_exists($sortBy, $sortFields)) {
            if ($sortBy === 'titulaire') {
                $query->join('clients', 'comptes.client_id', '=', 'clients.id')
                      ->orderBy('clients.nom', $sortOrder)
                      ->orderBy('clients.prenom', $sortOrder)
                      ->select('comptes.*');
            } else {
                $query->orderBy($sortFields[$sortBy], $sortOrder);
            }
        } else {
            $query->orderBy('created_at', 'desc');
        }

        // Pagination
        $perPage = min($request->get('limit', 10), 100);
        $comptes = $query->paginate($perPage);

        // Format response according to specifications
        $formattedData = $comptes->items();
        $formattedData = collect($formattedData)->map(function ($compte) {
            return [
                'id' => $compte->id,
                'numeroCompte' => $compte->numero_compte,
                'titulaire' => $compte->titulaire,
                'type' => $compte->type,
                'solde' => $compte->solde,
                'devise' => $compte->devise ?? 'FCFA',
                'dateCreation' => $compte->date_creation,
                'statut' => $compte->statut,
                'motifBlocage' => $compte->motifBlocage,
                'metadata' => [
                    'derniereModification' => $compte->derniere_modification,
                    'version' => $compte->metadata['version'] ?? 1,
                ],
            ];
        });

        $response = [
            'success' => true,
            'data' => $formattedData,
            'pagination' => [
                'currentPage' => $comptes->currentPage(),
                'totalPages' => $comptes->lastPage(),
                'totalItems' => $comptes->total(),
                'itemsPerPage' => $comptes->perPage(),
                'hasNext' => $comptes->hasMorePages(),
                'hasPrevious' => $comptes->currentPage() > 1,
            ],
            'links' => [
                'self' => $request->url() . '?' . $request->getQueryString(),
                'next' => $comptes->nextPageUrl(),
                'first' => $request->url() . '?' . http_build_query(array_merge($request->query(), ['page' => 1])),
                'last' => $request->url() . '?' . http_build_query(array_merge($request->query(), ['page' => $comptes->lastPage()])),
            ],
        ];

        // Remove null links
        if (!$comptes->hasMorePages()) {
            unset($response['links']['next']);
        }

        return response()->json($response, 200);
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