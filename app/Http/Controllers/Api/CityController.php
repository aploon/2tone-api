<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\City;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CityController extends Controller
{
    /**
     * List all cities (id, name).
     */
    public function index(): JsonResponse
    {
        $cities = City::orderBy('name')->get(['id', 'name']);

        return response()->json(['data' => $cities]);
    }

    /**
     * List neighborhoods of a city (paginated).
     */
    public function neighborhoods(Request $request, City $city): JsonResponse
    {
        $perPage = max(1, min(20, (int) $request->get('per_page', 5)));
        $neighborhoods = $city->neighborhoods()
            ->orderBy('name')
            ->paginate($perPage, ['id', 'name', 'city_id']);

        return response()->json($neighborhoods);
    }
}
