<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminListingController extends Controller
{
    /**
     * Liste paginée de toutes les annonces (tous statuts), filtres optionnels.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Listing::query()
            ->with([
                'neighborhood.city',
                'media',
                'owner:id,name,email,telephone,whatsapp_number',
            ])
            ->where('publication_status', '!=', Listing::STATUS_DRAFT);

        if ($request->filled('publication_status')) {
            $statuses = array_values(array_filter(array_map('trim', explode(',', (string) $request->publication_status))));
            if ($statuses !== []) {
                $query->whereIn('publication_status', $statuses);
            }
        }

        if ($request->filled('q')) {
            $q = '%'.addcslashes(trim((string) $request->q), '%_\\').'%';
            $query->where('title', 'like', $q);
        }

        $listings = $query
            ->orderByDesc('updated_at')
            ->paginate($request->integer('per_page', 20));

        return response()->json($listings);
    }

    /**
     * Modération : publier ou rejeter une annonce en attente de validation.
     */
    public function updatePublicationStatus(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'publication_status' => [
                'required',
                'string',
                Rule::in([Listing::STATUS_PUBLISHED, Listing::STATUS_REJECTED]),
            ],
        ]);

        $listing = Listing::query()->find($id);
        if (!$listing) {
            return response()->json(['message' => 'Annonce introuvable'], 404);
        }

        $listing->publication_status = $validated['publication_status'];
        $listing->save();

        return response()->json($listing->fresh(['neighborhood.city', 'media', 'owner:id,name,email,telephone,whatsapp_number']));
    }
}
