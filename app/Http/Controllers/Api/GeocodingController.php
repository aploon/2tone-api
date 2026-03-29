<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class GeocodingController extends Controller
{
    /**
     * Geocode an address using Nominatim (OpenStreetMap).
     *
     * Example:
     * GET /api/geocode?q=...&limit=1
     */
    public function search(Request $request): JsonResponse
    {
        $q = $request->get('q') ?? $request->get('address');
        $q = trim((string) $q);

        if ($q === '') {
            return response()->json(['message' => 'Missing query. Use ?q=...'], 422);
        }

        $limit = (int) $request->get('limit', 1);
        $limit = max(1, min(5, $limit));

        $userAgent = (string) env('NOMINATIM_USER_AGENT', '2TONE-geocoder/1.0');

        // Nominatim policy: set a meaningful User-Agent and keep rate under control.
        $res = Http::timeout(10)
            ->withHeaders([
                'User-Agent' => $userAgent,
            ])
            ->get('https://nominatim.openstreetmap.org/search', [
                'q' => $q,
                'format' => 'jsonv2',
                'addressdetails' => 1,
                'limit' => $limit,
            ]);

        if ($res->failed()) {
            return response()->json(['message' => 'Geocoding failed'], 502);
        }

        $data = $res->json();
        if (!is_array($data) || count($data) === 0) {
            return response()->json([
                'latitude' => null,
                'longitude' => null,
                'isPlaceholder' => true,
                'display_name' => null,
            ]);
        }

        $first = $data[0];
        $lat = isset($first['lat']) ? (float) $first['lat'] : null;
        $lon = isset($first['lon']) ? (float) $first['lon'] : null;

        return response()->json([
            'latitude' => $lat,
            'longitude' => $lon,
            'isPlaceholder' => $lat === null || $lon === null,
            'display_name' => $first['display_name'] ?? null,
        ]);
    }

    /**
     * Return multiple geocoding suggestions from Nominatim.
     *
     * Example:
     * GET /api/geocode/search?q=...&limit=5
     */
    public function searchSuggestions(Request $request): JsonResponse
    {
        $q = $request->get('q') ?? $request->get('address');
        $q = trim((string) $q);

        if ($q === '') {
            return response()->json(['results' => []]);
        }

        $limit = (int) $request->get('limit', 5);
        $limit = max(1, min(5, $limit));

        $userAgent = (string) env('NOMINATIM_USER_AGENT', '2TONE-geocoder/1.0');

        $res = Http::timeout(10)
            ->withHeaders([
                'User-Agent' => $userAgent,
            ])
            ->get('https://nominatim.openstreetmap.org/search', [
                'q' => $q,
                'format' => 'jsonv2',
                'addressdetails' => 1,
                'limit' => $limit,
            ]);

        if ($res->failed()) {
            return response()->json([
                'results' => [],
            ], 502);
        }

        $data = $res->json();
        if (!is_array($data) || count($data) === 0) {
            return response()->json(['results' => []]);
        }

        $results = array_map(function ($item) {
            $lat = isset($item['lat']) ? (float) $item['lat'] : null;
            $lon = isset($item['lon']) ? (float) $item['lon'] : null;

            return [
                'latitude' => $lat,
                'longitude' => $lon,
                'display_name' => $item['display_name'] ?? null,
            ];
        }, $data);

        return response()->json(['results' => $results]);
    }
}

