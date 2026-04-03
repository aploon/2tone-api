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
     * List all neighborhoods of a city (ordered by name).
     */
    public function neighborhoods(City $city): JsonResponse
    {
        $neighborhoods = $city->neighborhoods()
            ->orderBy('name')
            ->get(['id', 'name', 'city_id']);

        return response()->json($neighborhoods);
    }
}
