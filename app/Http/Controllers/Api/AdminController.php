<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    use ApiResponseTrait;
    public function index()
    {
        $admins = Admin::all();
        return $this->successResponse($admins);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom' => 'required|string|max:255',
            'email' => 'required|email|unique:admins,email',
            'mot_de_passe' => 'required|string|min:8',
        ]);

        $admin = Admin::create($validated);
        return $this->successResponse($admin, 'Administrateur créé avec succès', 201);
    }

    public function show(Admin $admin)
    {
        return $this->successResponse($admin);
    }

    public function update(Request $request, Admin $admin)
    {
        $validated = $request->validate([
            'nom' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:admins,email,' . $admin->id,
            'mot_de_passe' => 'sometimes|required|string|min:8',
        ]);

        $admin->update($validated);
        return $this->successResponse($admin, 'Administrateur mis à jour avec succès');
    }

    public function destroy(Admin $admin)
    {
        $admin->delete();
        return $this->successResponse(null, 'Administrateur supprimé avec succès', 204);
    }
}