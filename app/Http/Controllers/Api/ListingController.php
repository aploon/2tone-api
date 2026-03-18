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
            ->visible()
            ->where('publication_status', Listing::STATUS_PUBLISHED);

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
            $terms = array_values(array_filter(preg_split('/\s+/', trim((string) $request->q))));

            if (! empty($terms)) {
                $scoreParts = [];
                $scoreBindings = [];

                foreach ($terms as $word) {
                    $term = '%'.$word.'%';
                    $scoreParts[] = "(CASE WHEN (
                        listings.title LIKE ?
                        OR EXISTS (
                            SELECT 1
                            FROM neighborhoods n
                            LEFT JOIN cities c ON c.id = n.city_id
                            WHERE n.id = listings.neighborhood_id
                              AND (n.name LIKE ? OR c.name LIKE ?)
                        )
                    ) THEN 1 ELSE 0 END)";

                    $scoreBindings[] = $term;
                    $scoreBindings[] = $term;
                    $scoreBindings[] = $term;
                }

                $scoreExpression = implode(' + ', $scoreParts);

                $scoredQuery = (clone $query)
                    ->select('listings.*')
                    ->selectRaw("({$scoreExpression}) as q_match_score", $scoreBindings);

                $topScore = (int) ((clone $scoredQuery)->orderByDesc('q_match_score')->limit(1)->value('q_match_score') ?? 0);

                if ($topScore > 0) {
                    $query
                        ->select('listings.*')
                        ->selectRaw("({$scoreExpression}) as q_match_score", $scoreBindings)
                        ->whereRaw("({$scoreExpression}) = ?", array_merge($scoreBindings, [$topScore]));
                } else {
                    $query->whereRaw('1 = 0');
                }
            }
        }

        $listings = $query
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

    public function getTypes(): JsonResponse
    {
        return response()->json(Listing::getTypes());
    }
}
