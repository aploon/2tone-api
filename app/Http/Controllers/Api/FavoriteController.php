<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\Media;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    /**
     * List the authenticated user's favorite listings.
     */
    public function index(Request $request): JsonResponse
    {
        $listings = $request->user()
            ->favorites()
            ->visible()
            ->with(['neighborhood.city', 'media'])
            ->get()
            ->map(fn (Listing $listing) => $this->formatListing($listing));

        return response()->json(['data' => $listings]);
    }

    /**
     * Add a listing to favorites.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'listing_id' => 'required|integer|exists:listings,id',
        ]);

        $user = $request->user();
        $listing = Listing::visible()
            ->with(['neighborhood.city', 'media'])
            ->findOrFail($request->listing_id);

        if (!$user->favorites()->where('listing_id', $listing->id)->exists()) {
            $user->favorites()->attach($listing->id);
        }

        return response()->json([
            'message' => 'Added to favorites',
            'listing' => $this->formatListing($listing),
        ], 201);
    }

    /**
     * Remove a listing from favorites.
     */
    public function destroy(Request $request, int $listingId): JsonResponse
    {
        $request->user()->favorites()->detach($listingId);

        return response()->json(['message' => 'Removed from favorites']);
    }

    private function formatListing(Listing $listing): array
    {
        $neighborhood = $listing->neighborhood;
        $cityName = $neighborhood?->city?->name ?? '';
        $location = $neighborhood
            ? implode(', ', array_filter([$neighborhood->name, $cityName]))
            : '';
        $media = $listing->media ?? [];
        $primaryImage = collect($media)->first(fn ($m) => $m->is_primary) ?? collect($media)->first(fn ($m) => $m->type === 'image');
        $imageUrl = $primaryImage?->url;
        $has3dVisit = collect($media)->contains(
            fn ($m) => in_array($m->type, [
                Media::TYPE_VIDEO_3D,
                Media::TYPE_MODEL_3D,
                Media::TYPE_PANORAMA_3D,
            ], true)
        );

        return [
            'id' => (string) $listing->id,
            'title' => $listing->title,
            'price' => $listing->price,
            'billing_period' => $listing->billing_period?->value ?? 'per_month',
            'currency' => 'FCFA',
            'location' => $location,
            'bedrooms' => $listing->bedrooms,
            'bathrooms' => $listing->bathrooms,
            'surface_area' => $listing->surface_sqm,
            'image_url' => $imageUrl,
            'has_3d_visit' => $has3dVisit,
        ];
    }
}
