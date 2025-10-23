<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\Request;

/**
 * @OA\Info(
 *     title="Bank Manager API",
 *     version="1.0.0",
 *     description="API for managing bank clients, accounts, transactions and admins"
 * )
 *
 * @OA\Server(
 *     url="http://localhost:8000/api",
 *     description="Local development server"
 * )
 */
class ClientController extends Controller
{
    /**
     * @OA\Get(
     *     path="/v1/clients",
     *     summary="Get all clients",
     *     tags={"Clients"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of clients",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="nom", type="string", example="Doe"),
     *                 @OA\Property(property="prenom", type="string", example="John"),
     *                 @OA\Property(property="email", type="string", format="email", example="john.doe@example.com"),
     *                 @OA\Property(property="telephone", type="string", example="+1234567890"),
     *                 @OA\Property(property="adresse", type="string", example="123 Main St"),
     *                 @OA\Property(property="nci", type="string", example="1234567890123456"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     )
     * )
     */
    public function index()
    {
        $clients = Client::all();
        return response()->json($clients, 200);
    }

    /**
     * @OA\Post(
     *     path="/v1/clients",
     *     summary="Create a new client",
     *     tags={"Clients"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"nom","prenom","email","telephone","adresse","password","nci"},
     *             @OA\Property(property="nom", type="string", example="Doe"),
     *             @OA\Property(property="prenom", type="string", example="John"),
     *             @OA\Property(property="email", type="string", format="email", example="john.doe@example.com"),
     *             @OA\Property(property="telephone", type="string", example="+1234567890"),
     *             @OA\Property(property="adresse", type="string", example="123 Main St"),
     *             @OA\Property(property="password", type="string", example="password123"),
     *             @OA\Property(property="nci", type="string", example="1234567890123456")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Client created successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="nom", type="string", example="Doe"),
     *             @OA\Property(property="prenom", type="string", example="John"),
     *             @OA\Property(property="email", type="string", format="email", example="john.doe@example.com"),
     *             @OA\Property(property="telephone", type="string", example="+1234567890"),
     *             @OA\Property(property="adresse", type="string", example="123 Main St"),
     *             @OA\Property(property="nci", type="string", example="1234567890123456"),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="updated_at", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom' => 'required|string|max:255',
            'prenom' => 'required|string|max:255',
            'email' => 'required|email|unique:clients,email',
            'telephone' => 'required|string|max:20',
            'adresse' => 'required|string|max:255',
            'password' => 'required|string|min:8',
            'nci' => 'required|string|max:20|unique:clients,nci',
        ]);

        $client = Client::create($validated);
        return response()->json($client, 201);
    }

    /**
     * @OA\Get(
     *     path="/v1/clients/{client}",
     *     summary="Get a specific client",
     *     tags={"Clients"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="client",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Client details",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="nom", type="string", example="Doe"),
     *             @OA\Property(property="prenom", type="string", example="John"),
     *             @OA\Property(property="email", type="string", format="email", example="john.doe@example.com"),
     *             @OA\Property(property="telephone", type="string", example="+1234567890"),
     *             @OA\Property(property="adresse", type="string", example="123 Main St"),
     *             @OA\Property(property="nci", type="string", example="1234567890123456"),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="updated_at", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Client not found"
     *     )
     * )
     */
    public function show(Client $client)
    {
        return response()->json($client, 200);
    }

    /**
     * @OA\Put(
     *     path="/v1/clients/{client}",
     *     summary="Update a client",
     *     tags={"Clients"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="client",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="nom", type="string", example="Doe"),
     *             @OA\Property(property="prenom", type="string", example="John"),
     *             @OA\Property(property="email", type="string", format="email", example="john.doe@example.com"),
     *             @OA\Property(property="telephone", type="string", example="+1234567890"),
     *             @OA\Property(property="adresse", type="string", example="123 Main St"),
     *             @OA\Property(property="password", type="string", example="password123"),
     *             @OA\Property(property="nci", type="string", example="1234567890123456")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Client updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="nom", type="string", example="Doe"),
     *             @OA\Property(property="prenom", type="string", example="John"),
     *             @OA\Property(property="email", type="string", format="email", example="john.doe@example.com"),
     *             @OA\Property(property="telephone", type="string", example="+1234567890"),
     *             @OA\Property(property="adresse", type="string", example="123 Main St"),
     *             @OA\Property(property="nci", type="string", example="1234567890123456"),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="updated_at", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Client not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function update(Request $request, Client $client)
    {
        $validated = $request->validate([
            'nom' => 'sometimes|required|string|max:255',
            'prenom' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:clients,email,' . $client->id,
            'telephone' => 'sometimes|required|string|max:20',
            'adresse' => 'sometimes|required|string|max:255',
            'password' => 'sometimes|required|string|min:8',
            'nci' => 'sometimes|required|string|max:20|unique:clients,nci,' . $client->id,
        ]);

        $client->update($validated);
        return response()->json($client, 200);
    }

    /**
     * @OA\Delete(
     *     path="/v1/clients/{client}",
     *     summary="Delete a client",
     *     tags={"Clients"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="client",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=204,
         description="Client deleted successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Client not found"
     *     )
     * )
     */
    public function destroy(Client $client)
    {
        $client->delete();
        return response()->json(null, 204);
    }
}

/**
 * @OA\Schema(
 *     schema="Client",
 *     type="object",
 *     title="Client",
 *     description="Client model",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="nom", type="string", example="Doe"),
 *     @OA\Property(property="prenom", type="string", example="John"),
 *     @OA\Property(property="email", type="string", format="email", example="john.doe@example.com"),
 *     @OA\Property(property="telephone", type="string", example="+1234567890"),
 *     @OA\Property(property="adresse", type="string", example="123 Main St"),
 *     @OA\Property(property="nci", type="string", example="1234567890123456"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */