<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\ListingCorrectionRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ListingCorrectionRequestController extends Controller
{
    /**
     * List corrections requested for current owner.
     */
    public function ownerIndex(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (!$user->isOwner()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $q = ListingCorrectionRequest::query()
            ->with(['listing.neighborhood.city', 'listing.media', 'admin:id,name'])
            ->where('owner_id', $user->id);

        if ($request->filled('status')) {
            $q->where('status', (string) $request->input('status'));
        }

        // Simple order by: open status first, then by created_at desc
        $q->orderBy('status', 'desc')->orderByDesc('created_at');

        $rows = $q->paginate($request->integer('per_page', 20));

        return response()->json($rows);
    }

    /**
     * Owner marks requested correction as done.
     * The associated listing returns to pending for re-review.
     */
    public function ownerValidate(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (!$user->isOwner()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $row = ListingCorrectionRequest::with('listing')
            ->where('owner_id', $user->id)
            ->find($id);

        if (!$row) {
            return response()->json(['message' => 'Correction request not found'], 404);
        }

        $row->status = ListingCorrectionRequest::STATUS_DONE;
        $row->save();

        if ($row->listing) {
            $row->listing->publication_status = Listing::STATUS_PENDING;
            $row->listing->save();
        }

        return response()->json([
            'validated' => true,
            'listing_id' => $row->listing_id,
            'listing_status' => $row->listing?->publication_status,
        ]);
    }

    /**
     * Admin creates a correction request for a listing.
     * Listing moves to correction_requested.
     */
    public function adminStore(Request $request, int $listingId): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (!$user->isAdmin()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string'],
        ]);

        $listing = Listing::query()->find($listingId);
        if (!$listing) {
            return response()->json(['message' => 'Listing not found'], 404);
        }

        $row = ListingCorrectionRequest::create([
            'listing_id' => $listing->id,
            'owner_id' => $listing->owner_id,
            'admin_id' => $user->id,
            'title' => $data['title'],
            'message' => $data['message'],
            'status' => ListingCorrectionRequest::STATUS_OPEN,
        ]);

        $listing->publication_status = Listing::STATUS_CORRECTION_REQUESTED;
        $listing->save();

        return response()->json($row->fresh(['listing', 'admin:id,name']), 201);
    }
}

