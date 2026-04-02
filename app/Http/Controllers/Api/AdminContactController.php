<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminContactController extends Controller
{
    /**
     * Returns a contact for the platform admin (email / phone / whatsapp).
     * Used by suspended users to reach support.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User|null $me */
        $me = $request->user();

        // Public for authenticated users; protects against anonymous spam.
        if (! $me) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $admin = User::query()
            ->where('role', User::ROLE_ADMIN)
            ->orderByDesc('created_at')
            ->first();

        if (! $admin) {
            return response()->json(['message' => 'Admin not found'], 404);
        }

        return response()->json([
            'email' => $admin->email,
            'telephone' => $admin->telephone,
            'whatsapp_number' => $admin->whatsapp_number,
        ]);
    }
}

