<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\ListingCorrectionRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class AdminDashboardController extends Controller
{
    /**
     * Vue d'ensemble pour le tableau de bord admin (compteurs).
     */
    public function index(): JsonResponse
    {
        $usersByRole = User::query()
            ->selectRaw('role, count(*) as c')
            ->groupBy('role')
            ->pluck('c', 'role');

        $listingsByStatus = Listing::query()
            ->selectRaw('publication_status, count(*) as c')
            ->groupBy('publication_status')
            ->pluck('c', 'publication_status');

        $correctionsOpen = ListingCorrectionRequest::query()
            ->where('status', ListingCorrectionRequest::STATUS_OPEN)
            ->count();

        return response()->json([
            'users' => [
                'total' => User::query()->count(),
                'by_role' => $usersByRole,
            ],
            'listings' => [
                'total' => Listing::query()->count(),
                'by_status' => $listingsByStatus,
            ],
            'corrections_open' => $correctionsOpen,
        ]);
    }
}
