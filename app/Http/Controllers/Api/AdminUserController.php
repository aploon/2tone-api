<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    /**
     * List users for admin (pagination + optional filters).
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User|null $me */
        $me = $request->user();
        if (! $me || ! $me->isAdmin()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $q = User::query()
            ->select(['id', 'name', 'email', 'role', 'status', 'telephone', 'whatsapp_number', 'created_at', 'updated_at']);

        if ($request->filled('q')) {
            $needle = '%'.addcslashes(trim((string) $request->input('q')), '%_\\').'%';
            $q->where(function ($sub) use ($needle) {
                $sub->where('name', 'like', $needle)
                    ->orWhere('email', 'like', $needle);
            });
        }

        if ($request->filled('role')) {
            $roles = array_values(array_filter(array_map('trim', explode(',', (string) $request->input('role')))));
            $q->whereIn('role', $roles);
        }

        if ($request->filled('status')) {
            $statuses = array_values(array_filter(array_map('trim', explode(',', (string) $request->input('status')))));
            $q->whereIn('status', $statuses);
        }

        $users = $q
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return response()->json($users);
    }

    /**
     * Update status for an existing user.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        /** @var User|null $me */
        $me = $request->user();
        if (! $me || ! $me->isAdmin()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $row = User::query()->find($id);
        if (! $row) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Par design : on ne peut jamais suspendre/réactiver un admin.
        if ($row->isAdmin() && $request->filled('status')) {
            return response()->json(['message' => 'Impossible de modifier le status d\'un admin'], 422);
        }

        $data = $request->validate([
            'status' => ['sometimes', 'string', Rule::in([User::STATUS_ACTIVE, User::STATUS_SUSPENDED])],
        ]);

        if (array_key_exists('status', $data)) {
            $row->status = $data['status'];
        }

        $row->save();

        return response()->json($row->fresh());
    }
}

