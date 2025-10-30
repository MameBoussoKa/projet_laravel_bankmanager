<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CompteController;
use App\Http\Controllers\Api\AdminController;
use L5Swagger\L5SwaggerServiceProvider;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/


Route::prefix('v1')->middleware(['api.rate.limit', 'api.user.rate.limit', 'api.response.format', 'rating'])->group(function () {

    Route::get('comptes', [CompteController::class, 'index'])->middleware(['logging']);
    Route::post('comptes', [CompteController::class, 'store'])->middleware(['logging']);
    Route::get('comptes/{numeroCompte}', [CompteController::class, 'show'])->middleware(['logging']);
    Route::patch('comptes/{numeroCompte}', [CompteController::class, 'update'])->middleware(['logging']);
    Route::delete('comptes/{numeroCompte}', [CompteController::class, 'destroy'])->middleware(['logging']);

    // Admin only endpoints
    Route::post('comptes/{numeroCompte}/bloquer', [CompteController::class, 'bloquer'])->middleware(['logging']);
    Route::post('comptes/{numeroCompte}/debloquer', [CompteController::class, 'debloquer'])->middleware(['logging']);
    Route::get('comptes/archives', [CompteController::class, 'archives'])->middleware(['logging']);

    Route::apiResource('admins', AdminController::class)->middleware(['logging']);
});
