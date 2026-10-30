<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\CauseController;

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
Route::prefix('admin')->name('admin.')->group(function () {
    Route::apiResource('causes', CauseController::class);
    Route::patch('causes/{cause}/featured', [CauseController::class, 'toggleFeatured']);
});
