<?php

declare(strict_types=1);

namespace App\Modules\Core\Http\Controllers\Api\V1;

use App\Models\User;
use App\Modules\Core\Http\Resources\MembershipResource;
use App\Modules\Core\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Profil et écoles accessibles : c'est ce qui alimente le sélecteur
 * d'école de l'app mobile.
 */
final class MeController
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $memberships = $user->memberships()
            ->usable()
            ->with(['tenant', 'user'])
            ->get()
            ->sortBy('tenant.name')
            ->values();

        return response()->json([
            'user' => new UserResource($user),
            'memberships' => MembershipResource::collection($memberships),
        ]);
    }
}
