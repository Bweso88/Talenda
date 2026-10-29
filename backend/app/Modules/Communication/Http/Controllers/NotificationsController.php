<?php

declare(strict_types=1);

namespace App\Modules\Communication\Http\Controllers;

use App\Models\User;
use App\Modules\Communication\Http\Resources\NotificationResource;
use App\Modules\Communication\Models\UserNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Notifications du compte dans l'école choisie (les 100 plus récentes).
 */
final class NotificationsController
{
    public function index(Request $request): JsonResponse
    {
        $notifications = $this->mine($request)->latest('id')->limit(100)->get();

        return response()->json([
            'data' => NotificationResource::collection($notifications),
            'unread_count' => $this->mine($request)->whereNull('read_at')->count(),
        ]);
    }

    public function read(Request $request, string $notification): Response
    {
        $this->mine($request)->where('uuid', $notification)->firstOrFail()->update(['read_at' => now()]);

        return response()->noContent();
    }

    public function readAll(Request $request): Response
    {
        $this->mine($request)->whereNull('read_at')->update(['read_at' => now()]);

        return response()->noContent();
    }

    /**
     * @return Builder<UserNotification>
     */
    private function mine(Request $request): Builder
    {
        /** @var User $user */
        $user = $request->user();

        return UserNotification::query()->where('user_id', $user->id);
    }
}
