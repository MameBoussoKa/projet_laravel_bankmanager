<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function index()
    {
        $admins = Admin::all();
        return response()->json($admins, 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom' => 'required|string|max:255',
            'email' => 'required|email|unique:admins,email',
            'mot_de_passe' => 'required|string|min:8',
        ]);

        $admin = Admin::create($validated);
        return response()->json($admin, 201);
    }

    public function show(Admin $admin)
    {
        return response()->json($admin, 200);
    }

    public function update(Request $request, Admin $admin)
    {
        $validated = $request->validate([
            'nom' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:admins,email,' . $admin->id,
            'mot_de_passe' => 'sometimes|required|string|min:8',
        ]);

        $admin->update($validated);
        return response()->json($admin, 200);
    }

    public function destroy(Admin $admin)
    {
        $admin->delete();
        return response()->json(null, 204);
    }
}