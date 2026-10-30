<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\CauseController;
use App\Http\Controllers\Api\PledgeController;

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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Routes admin pour les causes
Route::middleware(['auth:sanctum'])->prefix('admin')->name('admin.')->group(function () {
    Route::apiResource('causes', CauseController::class);
    Route::patch('causes/{cause}/featured', [CauseController::class, 'toggleFeatured']);
});

// Route publique de soumission de don
Route::post('pledges', [PledgeController::class, 'store']);

// Route publique de listing des causes
Route::get('causes', [CauseController::class, 'index']);