<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Compte;
use App\Models\Client;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

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
        // Custom validation logic for conditional client fields
        $validator = validator($request->all(), [
            'type' => 'required|string|in:courant,epargne',
            'soldeInitial' => 'required|numeric|min:10000',
            'devise' => 'required|string|max:10',
            'client' => 'required|array',
            'client.id' => 'nullable|integer|exists:clients,id',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $data = $request->all();
        $clientId = $data['client']['id'] ?? null;

        // Additional validation based on whether client exists or not
        if ($clientId) {
            // Using existing client - validate that client exists
            $client = Client::find($clientId);
            if (!$client) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'VALIDATION_ERROR',
                        'message' => 'Les données fournies sont invalides',
                        'details' => [
                            'client.id' => 'Le client spécifié n\'existe pas'
                        ],
                        'timestamp' => now()->toISOString(),
                        'path' => request()->path(),
                        'traceId' => uniqid()
                    ]
                ], 400);
            }
        } else {
            // Creating new client - validate all required fields
            $clientValidator = validator($data['client'], [
                'titulaire' => 'required|string|max:255',
                'email' => 'required|string|email|unique:clients,email',
                'telephone' => 'required|string|regex:/^\+221[76-8][0-9]{7}$/|unique:clients,telephone',
                'adresse' => 'required|string|max:500',
            ]);

            if ($clientValidator->fails()) {
                return $this->validationErrorResponse($clientValidator->errors());
            }

            // Create new client
            $generatedPassword = $this->generatePassword();
            $generatedCode = $this->generateCode();

            $clientData = [
                'nom' => explode(' ', $data['client']['titulaire'])[0] ?? '',
                'prenom' => trim(str_replace(explode(' ', $data['client']['titulaire'])[0] ?? '', '', $data['client']['titulaire'])),
                'email' => $data['client']['email'],
                'telephone' => $data['client']['telephone'],
                'adresse' => $data['client']['adresse'],
                'password' => bcrypt($generatedPassword),
                'nci' => $generatedCode,
            ];

            $client = Client::create($clientData);

            // Send email with password
            $this->sendAuthenticationEmail($client, $generatedPassword);

            // Send SMS with code
            $this->sendSMSCode($client, $generatedCode);
        }

        // Generate account number
        $numeroCompte = $this->generateAccountNumber();

        // Create account
        $compteData = [
            'numero_compte' => $numeroCompte,
            'type' => $data['type'],
            'solde' => $data['soldeInitial'],
            'statut' => 'actif',
            'client_id' => $client->id,
            'devise' => $data['devise'],
        ];

        $compte = Compte::create($compteData);

        // Format response according to specifications
        $formattedData = [
            'id' => $compte->id,
            'numeroCompte' => $compte->numero_compte,
            'titulaire' => $compte->titulaire,
            'type' => $compte->type,
            'solde' => $compte->solde,
            'devise' => $compte->devise,
            'dateCreation' => $compte->date_creation,
            'statut' => $compte->statut,
            'metadata' => [
                'derniereModification' => $compte->derniere_modification,
                'version' => $compte->metadata['version'] ?? 1,
            ],
        ];

        return response()->json($formattedData, 201);
    }

    public function show(Compte $compte)
    {
        $user = Auth::user();

        // Determine if user is admin or client
        $isAdmin = Admin::where('email', $user->email)->exists();
        $isClient = Client::where('email', $user->email)->exists();

        // If user is client, check if the account belongs to them
        if ($isClient) {
            $client = Client::where('email', $user->email)->first();
            if (!$client || $compte->client_id !== $client->id) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'FORBIDDEN',
                        'message' => 'Vous n\'avez pas accès à ce compte',
                        'details' => [
                            'compteId' => $compte->id
                        ],
                        'timestamp' => now()->toISOString(),
                        'path' => request()->path(),
                        'traceId' => uniqid()
                    ]
                ], 403);
            }
        }
        // Admin can access any account

        // Format response according to specifications
        $formattedData = [
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

        return response()->json($formattedData, 200);
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

    /**
     * Generate a unique account number
     */
    private function generateAccountNumber(): string
    {
        do {
            $numero = 'C' . str_pad(mt_rand(1, 99999999), 8, '0', STR_PAD_LEFT);
        } while (Compte::where('numero_compte', $numero)->exists());

        return $numero;
    }

    /**
     * Generate a secure password
     */
    private function generatePassword(): string
    {
        return Str::random(12);
    }

    /**
     * Generate a unique code for client
     */
    private function generateCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (Client::where('nci', $code)->exists());

        return $code;
    }

    /**
     * Send authentication email to client
     */
    private function sendAuthenticationEmail(Client $client, string $password): void
    {
        // TODO: Implement email sending
        // Mail::to($client->email)->send(new ClientAuthenticationMail($client, $password));
    }

    /**
     * Send SMS with code to client
     */
    private function sendSMSCode(Client $client, string $code): void
    {
        // TODO: Implement SMS sending
        // $this->smsService->send($client->telephone, "Votre code d'authentification: $code");
    }

    /**
     * Format validation errors according to API specifications
     */
    private function validationErrorResponse($errors)
    {
        $details = [];
        foreach ($errors->messages() as $field => $messages) {
            $details[$field] = $messages[0];
        }

        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'VALIDATION_ERROR',
                'message' => 'Les données fournies sont invalides',
                'details' => $details
            ]
        ], 400);
    }
}