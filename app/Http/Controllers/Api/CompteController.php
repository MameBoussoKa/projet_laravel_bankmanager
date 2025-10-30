<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CompteResource;
use App\Http\Resources\CompteCollection;
use App\Models\Compte;
use App\Models\Client;
use App\Models\Admin;
use App\Services\CloudStorageService;
use App\Services\SmsService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * @OA\Info(
 *     title="Bank Manager API",
 *     version="1.0.0",
 *     description="API for managing bank accounts without authentication"
 * )
 *
 * @OA\Server(
 *     url="http://127.0.0.1:8000/api",
 *     description="Local development server"
 * )
 *
 * @OA\Server(
 *     url="https://projet-laravel-bankmanager.onrender.com/api",
 *     description="Production API server"
 * )
 *
 * @OA\Tag(
 *     name="Comptes",
 *     description="API Endpoints for account management"
 * )
 */
class CompteController extends Controller
{
    use ApiResponseTrait;

    /**
     * @OA\Get(
     *     path="/v1/comptes",
     *     summary="Lister tous les comptes",
     *     description="Récupère la liste de tous les comptes avec possibilité de filtrage et pagination",
     *     operationId="getComptes",
     *     tags={"Comptes"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Numéro de page",
     *         required=false,
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Nombre d'éléments par page",
     *         required=false,
     *         @OA\Schema(type="integer", default=10, maximum=100)
     *     ),
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         description="Filtrer par type de compte",
     *         required=false,
     *         @OA\Schema(type="string", enum={"courant", "epargne"})
     *     ),
     *     @OA\Parameter(
     *         name="statut",
     *         in="query",
     *         description="Filtrer par statut",
     *         required=false,
     *         @OA\Schema(type="string", enum={"actif", "bloque", "ferme"})
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Recherche par titulaire ou numéro de compte",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="sort",
     *         in="query",
     *         description="Champ de tri",
     *         required=false,
     *         @OA\Schema(type="string", enum={"dateCreation", "solde", "titulaire"})
     *     ),
     *     @OA\Parameter(
     *         name="order",
     *         in="query",
     *         description="Ordre de tri",
     *         required=false,
     *         @OA\Schema(type="string", enum={"asc", "desc"}, default="desc")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des comptes récupérée avec succès",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="pagination", type="object"),
     *             @OA\Property(property="links", type="object"),
     *             @OA\Property(property="timestamp", type="string", format="date-time"),
     *             @OA\Property(property="path", type="string"),
     *             @OA\Property(property="traceId", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Non autorisé",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(
     *         response=429,
     *         description="Trop de requêtes",
     *         @OA\JsonContent(type="object")
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = Compte::with('client');

        // Apply default filters: exclude blocked and closed accounts by default
        $query->whereNotIn('statut', ['bloque', 'ferme']);

        // Apply filters
        if ($request->has('type') && $request->type) {
            // Map 'cheque' to 'courant' for backward compatibility
            $type = $request->type === 'cheque' ? 'courant' : $request->type;
            $query->where('type', $type);
        }

        if ($request->has('statut') && $request->statut) {
            // Override default filter if statut is explicitly provided
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
                // Use left join to avoid issues with accounts that might not have clients
                $query->leftJoin('clients', 'comptes.client_id', '=', 'clients.id')
                      ->orderByRaw("COALESCE(clients.nom, '') {$sortOrder}")
                      ->orderByRaw("COALESCE(clients.prenom, '') {$sortOrder}")
                      ->select('comptes.*');
            } else {
                $query->orderBy($sortFields[$sortBy], $sortOrder);
            }
        } else {
            $query->orderBy('created_at', 'desc');
        }

        // Handle archived accounts for savings type - Temporarily disabled
        // TODO: Implement proper cloud storage service configuration
        if ($request->has('include_archived') && $request->include_archived && $request->type === 'epargne') {
            // Return only local accounts for now
            $perPage = min($request->get('limit', 10), 100);
            $localComptes = $query->paginate($perPage);

            return $this->successResponse(new CompteCollection($localComptes));
        }

        // Pagination
        $perPage = min($request->get('limit', 10), 100);
        $comptes = $query->paginate($perPage);

        // Format response using Resource Collection
        return $this->successResponse(new CompteCollection($comptes));
    }

    /**
     * @OA\Post(
     *     path="/v1/comptes",
     *     summary="Créer un nouveau compte",
     *     description="Crée un nouveau compte bancaire. Si le client n'existe pas, il sera créé automatiquement avec génération de mot de passe et code.",
     *     operationId="createCompte",
     *     tags={"Comptes"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"type","soldeInitial","devise","client"},
     *             @OA\Property(property="type", type="string", enum={"courant", "epargne"}, example="courant"),
     *             @OA\Property(property="soldeInitial", type="number", format="float", minimum=10000, example=50000),
     *             @OA\Property(property="devise", type="string", maxLength=10, example="FCFA"),
     *             @OA\Property(
     *                 property="client",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="titulaire", type="string", nullable=true, example="Amadou Diallo"),
     *                 @OA\Property(property="email", type="string", format="email", nullable=true, example="amadou.diallo@example.com"),
     *                 @OA\Property(property="telephone", type="string", nullable=true, example="+221771234568"),
     *                 @OA\Property(property="adresse", type="string", nullable=true, example="Dakar, Sénégal")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Compte créé avec succès",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Compte créé avec succès"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="string", format="uuid", example="550e8400-e29b-41d4-a716-446655440000"),
     *                 @OA\Property(property="numeroCompte", type="string", example="C00123456"),
     *                 @OA\Property(property="titulaire", type="string", example="Amadou Diallo"),
     *                 @OA\Property(property="type", type="string", example="courant"),
     *                 @OA\Property(property="solde", type="number", format="float", example=50000),
     *                 @OA\Property(property="devise", type="string", example="FCFA"),
     *                 @OA\Property(property="dateCreation", type="string", format="date-time"),
     *                 @OA\Property(property="statut", type="string", example="actif")
     *             ),
     *             @OA\Property(property="timestamp", type="string", format="date-time"),
     *             @OA\Property(property="path", type="string"),
     *             @OA\Property(property="traceId", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Données invalides",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Non autorisé",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(
     *         response=429,
     *         description="Trop de requêtes",
     *         @OA\JsonContent(type="object")
     *     )
     * )
     */
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
                return $this->errorResponse('VALIDATION_ERROR', 'Les données fournies sont invalides', ['client.id' => 'Le client spécifié n\'existe pas'], 400);
            }
        } else {
            // Creating new client - validate all required fields
            $clientValidator = validator($data['client'], [
                'titulaire' => 'required|string|max:255',
                'email' => 'required|string|email|unique:clients,email',
                'telephone' => 'required|string|regex:/^\+221[0-9]{9}$/|unique:clients,telephone',
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
                'is_active' => false, // Client needs to activate account with code
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
            'statut' => 'actif',
            'client_id' => $client->id,
            'devise' => $data['devise'],
        ];

        $compte = Compte::create($compteData);

        // Create initial deposit transaction to set the balance
        $transaction = new \App\Models\Transaction([
            'type' => 'depot',
            'montant' => $data['soldeInitial'],
            'date' => now(),
            'statut' => 'valide',
            'compte_id' => $compte->id,
        ]);
        $transaction->save();

        // Format response using Resource
        return $this->successResponse(new CompteResource($compte), 'Compte créé avec succès', 201);
    }

    /**
     * @OA\Get(
     *     path="/v1/comptes/{numeroCompte}",
     *     summary="Récupérer un compte spécifique",
     *     description="Récupère les détails d'un compte spécifique par son numéro de compte. Recherche d'abord en local, puis dans le cloud si nécessaire.",
     *     operationId="getCompte",
     *     tags={"Comptes"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="numeroCompte",
     *         in="path",
     *         description="Numéro du compte à récupérer",
     *         required=true,
     *         @OA\Schema(type="string", example="C00123456")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Détails du compte récupérés avec succès",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="timestamp", type="string", format="date-time"),
     *             @OA\Property(property="path", type="string"),
     *             @OA\Property(property="traceId", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Accès refusé - Le compte n'appartient pas au client",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Compte non trouvé",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="object",
     *                 @OA\Property(property="code", type="string", example="COMPTE_NOT_FOUND"),
     *                 @OA\Property(property="message", type="string", example="Le compte avec le numéro spécifié n'existe pas"),
     *                 @OA\Property(property="details", type="object",
     *                     @OA\Property(property="numeroCompte", type="string", example="C00123456")
     *                 ),
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="path", type="string"),
     *                 @OA\Property(property="traceId", type="string")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Non autorisé",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(
     *         response=429,
     *         description="Trop de requêtes",
     *         @OA\JsonContent(type="object")
     *     )
     * )
     */
    public function show($numeroCompte)
    {
        // Try to find account locally first by numero_compte
        $compte = Compte::with('client')->where('numero_compte', $numeroCompte)->first();

        if ($compte) {

            // Check if account is archived (soft deleted)
            if ($compte->trashed()) {
                // For archived accounts, check cloud storage
                $cloudService = new CloudStorageService();
                $archivedAccount = $cloudService->getArchivedAccount($compte->id);

                if ($archivedAccount) {
                    // Return archived account data
                    return $this->successResponse($archivedAccount);
                }

                return $this->errorResponse('COMPTE_NOT_FOUND', 'Le compte archivé n\'a pas été trouvé', ['numeroCompte' => $numeroCompte], 404);
            }

            // Format response using Resource
            return $this->successResponse(new CompteResource($compte));
        }

        // Account not found locally, check cloud storage for archived accounts
        $cloudService = new CloudStorageService();
        $archivedAccount = $cloudService->getArchivedAccount($numeroCompte);

        if ($archivedAccount) {
            return $this->successResponse($archivedAccount);
        }

        // Account not found in local or cloud
        return $this->errorResponse('COMPTE_NOT_FOUND', 'Le compte avec le numéro spécifié n\'existe pas', ['numeroCompte' => $numeroCompte], 404);
    }

    /**
     * @OA\Patch(
     *     path="/v1/comptes/{compteId}",
     *     summary="Mettre à jour les informations du client",
     *     description="Met à jour les informations du client associé à un compte. Tous les champs sont optionnels mais au moins un champ doit être fourni.",
     *     operationId="updateClientInfo",
     *     tags={"Comptes"},
     *     security={},
     *     @OA\Parameter(
     *         name="compteId",
     *         in="path",
     *         description="ID du compte",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="titulaire", type="string", example="Amadou Diallo Junior"),
     *             @OA\Property(property="informationsClient", type="object",
     *                 @OA\Property(property="telephone", type="string", example="+221771234568"),
     *                 @OA\Property(property="email", type="string", format="email", example="amadou.diallo@example.com"),
     *                 @OA\Property(property="password", type="string", example="newpassword123"),
     *                 @OA\Property(property="nci", type="string", example="ABC12345")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Informations du client mises à jour avec succès",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Compte mis à jour avec succès"),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="timestamp", type="string", format="date-time"),
     *             @OA\Property(property="path", type="string"),
     *             @OA\Property(property="traceId", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Données invalides ou aucun champ fourni",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Non autorisé",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Accès refusé",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Compte non trouvé",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(
     *         response=429,
     *         description="Trop de requêtes",
     *         @OA\JsonContent(type="object")
     *     )
     * )
     */
    public function update(\App\Http\Requests\UpdateClientInfoRequest $request, $numeroCompte)
    {
        // Find account by numero_compte
        $compte = Compte::with('client')->where('numero_compte', $numeroCompte)->first();

        if (!$compte) {
            return $this->errorResponse('COMPTE_NOT_FOUND', 'Le compte avec le numéro spécifié n\'existe pas', ['numeroCompte' => $numeroCompte], 404);
        }

        $data = $request->validated();

        // Update client information if provided
        if (isset($data['informationsClient']) || isset($data['titulaire'])) {
            $client = $compte->client;

            if (!$client) {
                return $this->errorResponse('CLIENT_NOT_FOUND', 'Client associé au compte non trouvé', ['numeroCompte' => $numeroCompte], 404);
            }

            $clientData = [];

            // Update titulaire if provided
            if (isset($data['titulaire'])) {
                $clientData['nom'] = explode(' ', $data['titulaire'])[0] ?? '';
                $clientData['prenom'] = trim(str_replace(explode(' ', $data['titulaire'])[0] ?? '', '', $data['titulaire']));
            }

            // Update client information fields
            if (isset($data['informationsClient'])) {
                $clientInfo = $data['informationsClient'];

                if (isset($clientInfo['telephone'])) {
                    $clientData['telephone'] = $clientInfo['telephone'];
                }

                if (isset($clientInfo['email'])) {
                    $clientData['email'] = $clientInfo['email'];
                }

                if (isset($clientInfo['password'])) {
                    $clientData['password'] = bcrypt($clientInfo['password']);
                }

                if (isset($clientInfo['nci'])) {
                    $clientData['nci'] = $clientInfo['nci'];
                }
            }

            // Update metadata for versioning
            $clientData['metadata'] = array_merge($client->metadata ?? [], [
                'derniereModification' => now()->toISOString(),
                'version' => ($client->metadata['version'] ?? 1) + 1
            ]);

            $client->update($clientData);
        }

        // Update compte metadata
        $compte->update([
            'metadata' => array_merge($compte->metadata ?? [], [
                'derniereModification' => now()->toISOString(),
                'version' => ($compte->metadata['version'] ?? 1) + 1
            ])
        ]);

        // Reload relationships and return response
        $compte->load('client');
        return $this->successResponse(new CompteResource($compte), 'Compte mis à jour avec succès');
    }

    /**
     * @OA\Delete(
     *     path="/v1/comptes/{compteId}",
     *     summary="Supprimer un compte (soft delete)",
     *     description="Supprime un compte en effectuant un soft delete. Le compte est marqué comme fermé avec une date de fermeture.",
     *     operationId="deleteCompte",
     *     tags={"Comptes"},
     *     security={},
     *     @OA\Parameter(
     *         name="compteId",
     *         in="path",
     *         description="ID du compte à supprimer",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Compte supprimé avec succès",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Compte supprimé avec succès"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="string", format="uuid", example="550e8400-e29b-41d4-a716-446655440000"),
     *                 @OA\Property(property="numeroCompte", type="string", example="C00123456"),
     *                 @OA\Property(property="statut", type="string", example="ferme"),
     *                 @OA\Property(property="dateFermeture", type="string", format="date-time", example="2025-10-19T11:15:00Z")
     *             ),
     *             @OA\Property(property="timestamp", type="string", format="date-time"),
     *             @OA\Property(property="path", type="string"),
     *             @OA\Property(property="traceId", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Non autorisé",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Accès refusé - Seuls les administrateurs peuvent supprimer des comptes",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Compte non trouvé",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(
     *         response=429,
     *         description="Trop de requêtes",
     *         @OA\JsonContent(type="object")
     *     )
     * )
     */
    public function destroy($numeroCompte)
    {

        // Find account by numero_compte
        $compte = Compte::where('numero_compte', $numeroCompte)->first();

        if (!$compte) {
            return $this->errorResponse('COMPTE_NOT_FOUND', 'Le compte avec le numéro spécifié n\'existe pas', ['numeroCompte' => $numeroCompte], 404);
        }

        // Check if account is already closed
        if ($compte->statut === 'ferme') {
            return $this->errorResponse('ACCOUNT_ALREADY_CLOSED', 'Le compte est déjà fermé', ['numeroCompte' => $numeroCompte], 400);
        }

        // Check if account is active (only active accounts can be deleted)
        if ($compte->statut !== 'actif') {
            return $this->errorResponse('ACCOUNT_NOT_ACTIVE', 'Seuls les comptes actifs peuvent être supprimés', ['numeroCompte' => $numeroCompte], 400);
        }

        // Perform soft delete by updating status and setting closure date
        $compte->update([
            'statut' => 'ferme',
            'dateFermeture' => now(),
        ]);

        // Soft delete the account
        $compte->delete();

        // Format response data
        $responseData = [
            'id' => $compte->id,
            'numeroCompte' => $compte->numero_compte,
            'statut' => $compte->statut,
            'dateFermeture' => $compte->dateFermeture?->toISOString(),
        ];

        return $this->successResponse($responseData, 'Compte supprimé avec succès');
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
        Mail::to($client->email)->send(new \App\Mail\ClientAuthenticationMail($client, $password));
    }

    /**
     * Send SMS with code to client
     */
    private function sendSMSCode(Client $client, string $code): void
    {
        try {
            $smsService = new SmsService();
            $result = $smsService->sendAuthenticationCode($client->telephone, $code);

            if (!$result) {
                Log::warning("Échec de l'envoi du SMS au client {$client->id} ({$client->telephone})");
            }
        } catch (\Exception $e) {
            Log::error("Erreur lors de l'envoi du SMS au client {$client->id}: " . $e->getMessage());
            // Fallback: log the SMS content for development
            Log::info("SMS de secours - Code pour {$client->telephone}: {$code}");
        }
    }

    /**
     * @OA\Post(
     *     path="/v1/comptes/{compteId}/bloquer",
     *     summary="Bloquer un compte épargne",
     *     description="Bloque un compte épargne actif avec un motif et une durée spécifiée. Seuls les comptes épargne actifs peuvent être bloqués.",
     *     operationId="bloquerCompte",
     *     tags={"Comptes"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="compteId",
     *         in="path",
     *         description="ID du compte à bloquer",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"motif","duree","unite"},
     *             @OA\Property(property="motif", type="string", maxLength=255, example="Activité suspecte détectée"),
     *             @OA\Property(property="duree", type="integer", minimum=1, example=30),
     *             @OA\Property(property="unite", type="string", enum={"jours", "mois"}, example="mois")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Compte bloqué avec succès",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Compte bloqué avec succès"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="string", format="uuid", example="550e8400-e29b-41d4-a716-446655440000"),
     *                 @OA\Property(property="statut", type="string", example="bloque"),
     *                 @OA\Property(property="motifBlocage", type="string", example="Activité suspecte détectée"),
     *                 @OA\Property(property="dateBlocage", type="string", format="date-time"),
     *                 @OA\Property(property="dateDeblocagePrevue", type="string", format="date-time")
     *             ),
     *             @OA\Property(property="timestamp", type="string", format="date-time"),
     *             @OA\Property(property="path", type="string"),
     *             @OA\Property(property="traceId", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Données invalides ou compte déjà bloqué",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Accès refusé - Seuls les administrateurs peuvent bloquer des comptes",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Compte non trouvé",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Non autorisé",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(
     *         response=429,
     *         description="Trop de requêtes",
     *         @OA\JsonContent(type="object")
     *     )
     * )
     */
    public function bloquer(Request $request, $numeroCompte)
    {

        // Find account by numero_compte
        $compte = Compte::where('numero_compte', $numeroCompte)->first();

        if (!$compte) {
            return $this->errorResponse('COMPTE_NOT_FOUND', 'Le compte avec le numéro spécifié n\'existe pas', ['numeroCompte' => $numeroCompte], 404);
        }

        // Validate request data
        $validator = validator($request->all(), [
            'motif' => 'required|string|max:255',
            'duree' => 'required|integer|min:1',
            'unite' => 'required|string|in:jours,mois',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        // Check if account is a savings account and active
        if ($compte->type !== 'epargne') {
            return $this->errorResponse('INVALID_ACCOUNT_TYPE', 'Seuls les comptes épargne peuvent être bloqués', ['numeroCompte' => $numeroCompte], 400);
        }

        if ($compte->statut !== 'actif') {
            return $this->errorResponse('ACCOUNT_NOT_ACTIVE', 'Le compte doit être actif pour être bloqué', ['numeroCompte' => $numeroCompte], 400);
        }

        // Calculate unlock date
        $now = now();
        $duree = $request->duree;
        $unite = $request->unite;

        if ($unite === 'jours') {
            $dateDeblocagePrevue = $now->copy()->addDays($duree);
        } else { // mois
            $dateDeblocagePrevue = $now->copy()->addMonths($duree);
        }

        // Update account status and blocking information
        $compte->update([
            'statut' => 'bloque',
            'motifBlocage' => $request->motif,
            'dateBlocage' => $now,
            'dateDeblocagePrevue' => $dateDeblocagePrevue,
        ]);

        // Format response data
        $responseData = [
            'id' => $compte->id,
            'numeroCompte' => $compte->numero_compte,
            'statut' => $compte->statut,
            'motifBlocage' => $compte->motifBlocage,
            'dateBlocage' => $compte->dateBlocage?->toISOString(),
            'dateDeblocagePrevue' => $compte->dateDeblocagePrevue?->toISOString(),
        ];

        return $this->successResponse($responseData, 'Compte bloqué avec succès');
    }

    /**
     * @OA\Get(
     *     path="/v1/comptes/archives",
     *     summary="Lister les comptes archivés",
     *     description="Récupère la liste des comptes épargne archivés depuis le cloud storage.",
     *     operationId="getArchivedComptes",
     *     tags={"Comptes"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Numéro de page",
     *         required=false,
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Nombre d'éléments par page",
     *         required=false,
     *         @OA\Schema(type="integer", default=10, maximum=100)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des comptes archivés récupérée avec succès",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="pagination", type="object"),
     *             @OA\Property(property="timestamp", type="string", format="date-time"),
     *             @OA\Property(property="path", type="string"),
     *             @OA\Property(property="traceId", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Non autorisé",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Accès refusé - Seuls les administrateurs peuvent voir les archives",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(
     *         response=429,
     *         description="Trop de requêtes",
     *         @OA\JsonContent(type="object")
     *     )
     * )
     */
    public function archives(Request $request)
    {

        // Get archived accounts from cloud storage
        $cloudService = new CloudStorageService();
        $archivedAccounts = $cloudService->getArchivedSavingsAccounts();

        // Apply pagination
        $perPage = min($request->get('limit', 10), 100);
        $page = $request->get('page', 1);
        $offset = ($page - 1) * $perPage;

        $paginatedAccounts = array_slice($archivedAccounts, $offset, $perPage);
        $totalItems = count($archivedAccounts);
        $totalPages = ceil($totalItems / $perPage);

        $paginationData = [
            'currentPage' => (int) $page,
            'totalPages' => $totalPages,
            'totalItems' => $totalItems,
            'itemsPerPage' => $perPage,
            'hasNext' => $page < $totalPages,
            'hasPrevious' => $page > 1,
        ];

        return $this->successResponse($paginatedAccounts, null, 200, $paginationData);
    }

    /**
     * @OA\Post(
     *     path="/v1/comptes/{numeroCompte}/debloquer",
     *     summary="Débloquer un compte épargne",
     *     description="Débloque un compte épargne bloqué avec un motif de déblocage.",
     *     operationId="debloquerCompte",
     *     tags={"Comptes"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="numeroCompte",
     *         in="path",
     *         description="Numéro du compte à débloquer",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"motif"},
     *             @OA\Property(property="motif", type="string", maxLength=255, example="Vérification complétée")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Compte débloqué avec succès",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Compte débloqué avec succès"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="numeroCompte", type="string", example="C00123456"),
     *                 @OA\Property(property="statut", type="string", example="actif"),
     *                 @OA\Property(property="dateDeblocage", type="string", format="date-time")
     *             ),
     *             @OA\Property(property="timestamp", type="string", format="date-time"),
     *             @OA\Property(property="path", type="string"),
     *             @OA\Property(property="traceId", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Données invalides ou compte non bloqué",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Accès refusé - Seuls les administrateurs peuvent débloquer des comptes",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Compte non trouvé",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Non autorisé",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(
     *         response=429,
     *         description="Trop de requêtes",
     *         @OA\JsonContent(type="object")
     *     )
     * )
     */
    public function debloquer(Request $request, $numeroCompte)
    {

        // Find account by numero_compte
        $compte = Compte::where('numero_compte', $numeroCompte)->first();

        if (!$compte) {
            return $this->errorResponse('COMPTE_NOT_FOUND', 'Le compte avec le numéro spécifié n\'existe pas', ['numeroCompte' => $numeroCompte], 404);
        }

        // Validate request data
        $validator = validator($request->all(), [
            'motif' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        // Check if account is blocked
        if ($compte->statut !== 'bloque') {
            return $this->errorResponse('ACCOUNT_NOT_BLOCKED', 'Le compte n\'est pas bloqué', ['numeroCompte' => $numeroCompte], 400);
        }

        // Update account status and clear blocking information
        $compte->update([
            'statut' => 'actif',
            'motifBlocage' => null,
            'dateBlocage' => null,
            'dateDeblocagePrevue' => null,
        ]);

        // Format response data
        $responseData = [
            'id' => $compte->id,
            'numeroCompte' => $compte->numero_compte,
            'statut' => $compte->statut,
            'dateDeblocage' => now()->toISOString(),
        ];

        return $this->successResponse($responseData, 'Compte débloqué avec succès');
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

        return $this->errorResponse('VALIDATION_ERROR', 'Les données fournies sont invalides', $details, 400);
    }
}