<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TwilioVerifyService;
use App\Support\PhoneE164;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        $user = User::where('email', $request->email)->firstOrFail();
        $user->tokens()->delete();
        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->formatUser($user),
        ]);
    }

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            /** Compte locataire / utilisateur (recherche, favoris) ou propriétaire (publication d'annonces). */
            'role' => ['sometimes', 'string', Rule::in([User::ROLE_TENANT, User::ROLE_OWNER])],
            /** Obligatoire pour les propriétaires : utilisé comme téléphone et numéro WhatsApp. */
            'telephone' => [
                Rule::requiredIf(fn () => ($request->input('role') ?? User::ROLE_TENANT) === User::ROLE_OWNER),
                'nullable',
                'string',
                'max:30',
            ],
        ]);

        $role = $validated['role'] ?? User::ROLE_TENANT;
        $phoneRaw = isset($validated['telephone']) ? trim((string) $validated['telephone']) : '';

        if ($role === User::ROLE_TENANT) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role' => $role,
                'status' => User::STATUS_ACTIVE,
                'telephone' => null,
                'whatsapp_number' => null,
            ]);
        } else {
            $phoneE164 = PhoneE164::normalize($phoneRaw);
            if ($phoneE164 === null) {
                return response()->json([
                    'message' => 'Numéro de téléphone invalide.',
                ], 422);
            }

            $phoneAlreadyUsed = User::query()
                ->where(function ($q) use ($phoneE164): void {
                    $q->where('telephone', $phoneE164)
                        ->orWhere('whatsapp_number', $phoneE164);
                })
                ->exists();

            if ($phoneAlreadyUsed) {
                return response()->json([
                    'message' => 'Ce numéro de téléphone est déjà associé à un compte.',
                ], 422);
            }

            $verifySkip = filter_var(config('services.twilio.verify_skip'), FILTER_VALIDATE_BOOLEAN);
            $verify = app(TwilioVerifyService::class);

            if (! $verifySkip && ! $verify->isConfigured()) {
                return response()->json([
                    'message' => 'Vérification SMS indisponible (configuration Twilio manquante).',
                ], 503);
            }

            $status = $verifySkip ? User::STATUS_ACTIVE : User::STATUS_PENDING_OTP;

            try {
                $user = DB::transaction(function () use ($validated, $role, $phoneE164, $status, $verifySkip, $verify) {
                    $user = User::create([
                        'name' => $validated['name'],
                        'email' => $validated['email'],
                        'password' => Hash::make($validated['password']),
                        'role' => $role,
                        'status' => $status,
                        'telephone' => $phoneE164,
                        'whatsapp_number' => $phoneE164,
                    ]);

                    if (!$verifySkip) {
                        $verify->sendVerification($phoneE164);
                    }

                    return $user;
                });
            } catch (\Throwable $e) {
                report($e);
                Log::error('Erreur lors de l\'envoi du SMS de vérification Twilio', [
                    'exception' => $e,
                    'user_email' => $validated['email'] ?? null,
                    'role' => $role ?? null,
                    'phone' => $phoneE164 ?? null,
                ]);

                return response()->json([
                    'message' => 'Impossible d’envoyer le SMS de vérification. Vérifiez votre numéro de téléphone et réessayez.',
                ], 502);
            }
        }

        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->formatUser($user),
        ], 201);
    }

    /**
     * Renvoie un code SMS (propriétaire en attente de vérification).
     */
    public function sendPhoneOtp(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->role !== User::ROLE_OWNER || $user->status !== User::STATUS_PENDING_OTP) {
            return response()->json(['message' => 'Aucune vérification en attente.'], 422);
        }

        $phone = $user->telephone;
        if ($phone === null || $phone === '') {
            return response()->json(['message' => 'Numéro de téléphone manquant.'], 422);
        }

        $verify = app(TwilioVerifyService::class);
        if (! $verify->isConfigured()) {
            return response()->json(['message' => 'Vérification SMS indisponible.'], 503);
        }

        try {
            $verify->sendVerification($phone);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => 'Impossible d’envoyer le SMS. Réessayez plus tard.'], 502);
        }

        return response()->json(['message' => 'SMS envoyé.']);
    }

    /**
     * Valide le code Twilio Verify et active le compte propriétaire.
     */
    public function verifyPhone(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'min:4', 'max:10'],
        ]);

        /** @var User $user */
        $user = $request->user();

        if ($user->role !== User::ROLE_OWNER || $user->status !== User::STATUS_PENDING_OTP) {
            return response()->json(['message' => 'Aucune vérification en attente.'], 422);
        }

        $phone = $user->telephone;
        if ($phone === null || $phone === '') {
            return response()->json(['message' => 'Numéro de téléphone manquant.'], 422);
        }

        $verify = app(TwilioVerifyService::class);
        if (! $verify->isConfigured()) {
            return response()->json(['message' => 'Vérification SMS indisponible.'], 503);
        }

        try {
            $ok = $verify->checkVerification($phone, $data['code']);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => 'Vérification impossible. Réessayez.'], 502);
        }

        if (! $ok) {
            return response()->json(['message' => 'Code incorrect ou expiré.'], 422);
        }

        $user->status = User::STATUS_ACTIVE;
        $user->save();

        return response()->json([
            'message' => 'Numéro confirmé.',
            'user' => $this->formatUser($user->fresh()),
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'telephone' => ['nullable', 'string', 'max:30'],
            'whatsapp_number' => ['nullable', 'string', 'max:30'],
        ]);

        $user->update($validated);

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $this->formatUser($user->fresh()),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out']);
    }

    private function formatUser(User $user): array
    {
        return [
            'id' => (string) $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'role' => $user->role,
            'status' => $user->status,
            'telephone' => $user->telephone,
            'whatsapp_number' => $user->whatsapp_number,
        ];
    }
}
