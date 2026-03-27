<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreListingRequest;
use App\Models\Listing;
use App\Models\Media;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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

        if ($request->filled('owner_id')) {
            $query->where('owner_id', $request->owner_id);
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
                    $scoreParts[] = '(CASE WHEN (
                        listings.title LIKE ?
                        OR EXISTS (
                            SELECT 1
                            FROM neighborhoods n
                            LEFT JOIN cities c ON c.id = n.city_id
                            WHERE n.id = listings.neighborhood_id
                              AND (n.name LIKE ? OR c.name LIKE ?)
                        )
                    ) THEN 1 ELSE 0 END)';

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

    /**
     * Enregistre une annonce après validation du paiement (simulation ou future intégration gateway).
     */
    public function store(StoreListingRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->isOwner()) {
            return response()->json(['message' => 'Seuls les propriétaires peuvent publier une annonce.'], 403);
        }

        /**
         * @todo Payer : vérifier ici le paiement réel (montant, référence gateway, idempotence) avant toute écriture en base.
         * Pour les tests, le client envoie payment_confirmed: true uniquement après le bouton « Payer ».
         */
        $data = $request->validated();
        unset($data['payment_confirmed']);

        $mediaItems = $data['media'] ?? [];
        unset($data['media']);

        $hasPrimaryImage = false;
        foreach ($mediaItems as $item) {
            $isPrimary = (bool) ($item['is_primary'] ?? false);
            $isImage = ($item['type'] ?? null) === Media::TYPE_IMAGE;
            if ($isPrimary && $isImage) {
                $hasPrimaryImage = true;
                break;
            }
        }

        if (! $hasPrimaryImage) {
            return response()->json([
                'message' => 'Une image de couverture est obligatoire (is_primary=true).',
            ], 422);
        }

        $fee = (float) config('listing.publication_fee', 5000);

        $listing = DB::transaction(function () use ($user, $data, $mediaItems, $fee) {
            $listing = Listing::create([
                'owner_id' => $user->id,
                'neighborhood_id' => $data['neighborhood_id'],
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'type' => $data['type'],
                'price' => $data['price'],
                'billing_period' => $data['billing_period'],
                'publication_status' => Listing::STATUS_PUBLISHED,
                'bedrooms' => $data['bedrooms'] ?? 0,
                'bathrooms' => $data['bathrooms'] ?? 0,
                'surface_sqm' => $data['surface_sqm'] ?? null,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
            ]);

            foreach ($mediaItems as $index => $item) {
                Media::create([
                    'listing_id' => $listing->id,
                    'type' => $item['type'],
                    'url' => $item['url'],
                    'is_primary' => (bool) ($item['is_primary'] ?? false),
                    'sort_order' => (int) ($item['sort_order'] ?? $index),
                ]);
            }

            Payment::create([
                'listing_id' => $listing->id,
                'amount' => $fee,
                'status' => Payment::STATUS_COMPLETED,
                'method' => 'simulated',
                'reference' => 'SIM-'.Str::uuid()->toString(),
                'paid_at' => now(),
            ]);

            return $listing->fresh(['neighborhood.city', 'media']);
        });

        return response()->json($listing, 201);
    }

    /**
     * Upload d’un fichier média pour une future annonce.
     * Le comportement dépend d’un champ optionnel `target` envoyé par l’app :
     * - `images` : n’accepte que les images (max 10 Mo), stockées dans `listings/photos/...`
     * - `video_3d` : accepte tout type de fichier (max 50 Mo), stocké dans `listings/video-3d/...`
     *   sauf `glb/gltf` qui deviennent `model_3d` stockés dans `listings/models-3d/...`
     */
    public function uploadMedia(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->isOwner()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $request->validate([
            'file' => ['required', 'file'],
            'target' => ['sometimes', 'string', 'in:images,video_3d'],
        ]);

        $file = $request->file('file');
        $mime = (string) $file->getMimeType();
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $sizeKb = (int) ceil($file->getSize() / 1024);

        $target = $request->get('target');

        $subDir = date('Y/m');
        $mediaType = null;

        // 1) Comportement explicite par target (recommandé, cohérent avec l’UI).
        if ($target === 'images') {
            if (!str_starts_with($mime, 'image/')) {
                return response()->json(['message' => 'Le fichier doit être une image.'], 422);
            }
            if ($sizeKb > 10240) {
                return response()->json(['message' => 'Image trop lourde (max. 10 Mo).'], 422);
            }
            $mediaType = Media::TYPE_IMAGE;
            $path = $file->store("listings/photos/{$subDir}", 'public');
        } elseif ($target === 'video_3d') {
            if ($sizeKb > 51200) {
                return response()->json(['message' => 'Fichier trop lourd (max. 50 Mo).'], 422);
            }

            // Modèle 3D : glb/gltf => model_3d.
            if (in_array($extension, ['glb', 'gltf'], true)) {
                $mediaType = Media::TYPE_MODEL_3D;
                $path = $file->store("listings/models-3d/{$subDir}", 'public');
            } else {
                // Tout le reste => video_3d (même si MIME = image/, etc.).
                $mediaType = Media::TYPE_VIDEO_3D;
                $path = $file->store("listings/video-3d/{$subDir}", 'public');
            }
        } else {
            // 2) Rétro-compatibilité : comportement historique quand `target` n’est pas fourni.
            if (str_starts_with($mime, 'image/')) {
                if ($sizeKb > 10240) {
                    return response()->json(['message' => 'Image trop lourde (max. 10 Mo).'], 422);
                }
                $mediaType = Media::TYPE_IMAGE;
                $path = $file->store("listings/photos/{$subDir}", 'public');
            } elseif (str_starts_with($mime, 'video/')) {
                if ($sizeKb > 51200) {
                    return response()->json(['message' => 'Vidéo trop lourde (max. 50 Mo).'], 422);
                }
                $mediaType = Media::TYPE_VIDEO_3D;
                $path = $file->store("listings/video-3d/{$subDir}", 'public');
            } elseif (in_array($extension, ['glb', 'gltf'], true)) {
                if ($sizeKb > 51200) {
                    return response()->json(['message' => 'Fichier 3D trop lourd (max. 50 Mo).'], 422);
                }
                $mediaType = Media::TYPE_MODEL_3D;
                $path = $file->store("listings/models-3d/{$subDir}", 'public');
            } else {
                if ($sizeKb > 51200) {
                    return response()->json(['message' => 'Fichier trop lourd (max. 50 Mo).'], 422);
                }
                $mediaType = Media::TYPE_VIDEO_3D;
                $path = $file->store("listings/video-3d/{$subDir}", 'public');
            }
        }

        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('public');
        $url = $disk->url($path);

        return response()->json([
            'url' => $url,
            'path' => $path,
            'type' => $mediaType,
        ]);
    }

    /**
     * Supprime un média uploadé temporairement (avant création de la fiche).
     * Utile quand l'utilisateur retire un fichier via l'icône "X" du formulaire.
     */
    public function deleteMedia(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->isOwner()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'path' => ['required', 'string'],
        ]);

        $path = (string) $data['path'];

        // Prevent path traversal / arbitrary deletion.
        if (str_contains($path, '..')) {
            return response()->json(['message' => 'Chemin invalide.'], 422);
        }

        $allowedPrefixes = [
            'listings/photos/',
            'listings/video-3d/',
            'listings/models-3d/',
        ];

        $allowed = false;
        foreach ($allowedPrefixes as $prefix) {
            if (str_starts_with($path, $prefix)) {
                $allowed = true;
                break;
            }
        }

        if (! $allowed) {
            return response()->json(['message' => 'Chemin non autorisé.'], 422);
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($path)) {
            return response()->json(['message' => 'Fichier introuvable.'], 404);
        }

        $disk->delete($path);

        return response()->json(['deleted' => true]);
    }
}
