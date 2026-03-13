<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ListingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Listing::query()
            ->with(['neighborhood', 'media', 'owner:id,name,telephone,whatsapp_number'])
            ->visible();

        if ($request->filled('neighborhood_id')) {
            $query->where('neighborhood_id', $request->neighborhood_id);
        }

        if ($request->filled('property_type')) {
            $query->where('property_type', $request->property_type);
        }

        if ($request->filled('min_price')) {
            $query->where('price', '>=', (int) $request->min_price);
        }

        if ($request->filled('max_price')) {
            $query->where('price', '<=', (int) $request->max_price);
        }

        $listings = $query->orderByDesc('created_at')->paginate(
            $request->integer('per_page', 15)
        );

        return response()->json($listings);
    }

    public function show(int $id): JsonResponse
    {
        $listing = Listing::with(['neighborhood', 'media', 'owner:id,name,telephone,whatsapp_number'])
            ->visible()
            ->find($id);

        if (! $listing) {
            return response()->json(['message' => 'Listing not found'], 404);
        }

        return response()->json($listing);
    }
}
