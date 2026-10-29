<?php

declare(strict_types=1);

namespace App\Modules\Core\Http\Controllers\Api\V1;

use App\Models\User;
use App\Modules\Core\Actions\RecordAudit;
use App\Modules\Core\Auth\LoginIdentifier;
use App\Modules\Core\Http\Requests\LoginRequest;
use App\Modules\Core\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Connexion de l'app mobile : un jeton Sanctum par appareil
 * (docs/PRODUCT_ARCHITECTURE.md §10, §14).
 */
final class AuthController
{
    public function login(LoginRequest $request, RecordAudit $recordAudit): JsonResponse
    {
        $credentials = LoginIdentifier::toCredentials($request->string('login')->toString());

        $user = $credentials === null ? null : User::query()->where($credentials)->first();

        if ($user === null || ! Hash::check($request->string('password')->toString(), $user->password)) {
            throw ValidationException::withMessages([
                'login' => 'Identifiants incorrects.',
            ]);
        }

        $token = $user->createToken($request->string('device_name')->toString());

        $recordAudit->handle('auth.api_login', actor: $user, metadata: [
            'device_name' => $request->string('device_name')->toString(),
        ]);

        return response()->json([
            'token' => $token->plainTextToken,
            'user' => new UserResource($user),
        ]);
    }

    public function logout(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $user->currentAccessToken()->delete();

        return response()->noContent();
    }
}
