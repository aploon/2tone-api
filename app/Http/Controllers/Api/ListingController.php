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
            ->with(['neighborhood.city', 'media', 'owner:id,name,telephone,whatsapp_number'])
            ->visible();

        if ($request->filled('neighborhood_id')) {
            $query->where('neighborhood_id', $request->neighborhood_id);
        }

        if ($request->filled('city_id')) {
            $query->whereHas('neighborhood', function ($nq) use ($request) {
                $nq->where('city_id', $request->city_id);
            });
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('min_price')) {
            $query->where('price', '>=', (int) $request->min_price);
        }

        if ($request->filled('max_price')) {
            $query->where('price', '<=', (int) $request->max_price);
        }

        if ($request->filled('q')) {
            $term = '%'.trim($request->q).'%';
            $query->where(function ($q) use ($term) {
                $q->where('title', 'like', $term)
                    ->orWhereHas('neighborhood', function ($nq) use ($term) {
                        $nq->where('name', 'like', $term)
                            ->orWhereHas('city', function ($cq) use ($term) {
                                $cq->where('name', 'like', $term);
                            });
                    });
            });
        }

        $listings = $query
            ->where('publication_status', Listing::STATUS_PUBLISHED)
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return response()->json($listings);
    }

    public function show(int $id): JsonResponse
    {
        $listing = Listing::with(['neighborhood.city', 'media', 'owner:id,name,telephone,whatsapp_number'])
            ->visible()
            ->find($id);

        if (! $listing) {
            return response()->json(['message' => 'Listing not found'], 404);
        }

        return response()->json($listing);
    }
}
