<?php

use App\Http\Controllers\Api\AdminDashboardController;
use App\Http\Controllers\Api\AdminListingController;
use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CityController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\GeocodingController;
use App\Http\Controllers\Api\ListingController;
use App\Http\Controllers\Api\ListingCorrectionRequestController;
use App\Http\Controllers\Api\ListingPublicationPaymentController;
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

Route::get('/payments/callback/{gateway}', [ListingPublicationPaymentController::class, 'callback']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/listings', [ListingController::class, 'store']);
    Route::get('/listings/{id}/payment/methods', [ListingPublicationPaymentController::class, 'methods']);
    Route::post('/listings/{id}/payment/initiate', [ListingPublicationPaymentController::class, 'initiate']);
    Route::get('/listings/{id}/payment/status', [ListingPublicationPaymentController::class, 'status']);
    Route::put('/listings/{id}', [ListingController::class, 'update']);
    Route::delete('/listings/{id}', [ListingController::class, 'destroy']);
    Route::post('/listings/media', [ListingController::class, 'uploadMedia']);
    Route::delete('/listings/media', [ListingController::class, 'deleteMedia']);
    Route::get('/owner/listing-corrections', [ListingCorrectionRequestController::class, 'ownerIndex']);
    Route::post('/owner/listing-corrections/{id}/validate', [ListingCorrectionRequestController::class, 'ownerValidate']);
    Route::get('/favorites', [FavoriteController::class, 'index']);
    Route::post('/favorites', [FavoriteController::class, 'store']);
    Route::delete('/favorites/{listingId}', [FavoriteController::class, 'destroy']);
});

Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::get('/admin/dashboard', [AdminDashboardController::class, 'index']);
    Route::get('/admin/listings', [AdminListingController::class, 'index']);
    Route::patch('/admin/listings/{id}/publication-status', [AdminListingController::class, 'updatePublicationStatus']);
    Route::post('/admin/listings/{listingId}/corrections', [ListingCorrectionRequestController::class, 'adminStore']);
    Route::get('/admin/listing-corrections', [ListingCorrectionRequestController::class, 'adminIndex']);
    Route::post('/admin/listing-corrections/{id}/validate', [ListingCorrectionRequestController::class, 'adminValidate']);
    Route::get('/admin/users', [AdminUserController::class, 'index']);
    Route::patch('/admin/users/{id}', [AdminUserController::class, 'update']);
});
