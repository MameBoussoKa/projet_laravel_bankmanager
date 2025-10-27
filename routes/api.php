<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CompteController;
use App\Http\Controllers\Api\TransactionController;
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

Route::get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('v1')->middleware(['api.rate.limit', 'api.user.rate.limit', 'api.response.format', 'rating'])->group(function () {
    
    Route::apiResource('comptes', CompteController::class)->middleware(['logging']);
    Route::post('comptes/{compte}/bloquer', [CompteController::class, 'bloquer'])->middleware(['logging']);
    Route::post('comptes/{compte}/debloquer', [CompteController::class, 'debloquer'])->middleware(['logging']);
    Route::apiResource('transactions', TransactionController::class);
    Route::apiResource('admins', AdminController::class);
});
