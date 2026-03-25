<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CityController;
use App\Http\Controllers\Api\GeocodingController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\ListingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

Route::get('/user', function (Request $request) {
    $user = $request->user();

    return response()->json([
        'id' => (string) $user->id,
        'email' => $user->email,
        'name' => $user->name,
        'role' => $user->role,
        'telephone' => $user->telephone,
        'whatsapp_number' => $user->whatsapp_number,
    ]);
})->middleware('auth:sanctum');

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::put('/user', [AuthController::class, 'updateProfile'])->middleware('auth:sanctum');

Route::get('/listings', [ListingController::class, 'index']);
Route::get('/listings/types', [ListingController::class, 'getTypes']);
Route::get('/listings/{id}', [ListingController::class, 'show']);

Route::get('/cities', [CityController::class, 'index']);
Route::get('/cities/{city}/neighborhoods', [CityController::class, 'neighborhoods']);

Route::get('/geocode', [GeocodingController::class, 'search']);
Route::get('/geocode/search', [GeocodingController::class, 'searchSuggestions']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/listings', [ListingController::class, 'store']);
    Route::post('/listings/media', [ListingController::class, 'uploadMedia']);
    Route::delete('/listings/media', [ListingController::class, 'deleteMedia']);
    Route::get('/favorites', [FavoriteController::class, 'index']);
    Route::post('/favorites', [FavoriteController::class, 'store']);
    Route::delete('/favorites/{listingId}', [FavoriteController::class, 'destroy']);
});
