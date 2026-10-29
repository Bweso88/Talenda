<?php

declare(strict_types=1);

namespace App\Modules\Communication\Http\Controllers;

use App\Models\User;
use App\Modules\Communication\Models\DeviceToken;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * Enregistrement du jeton push de l'appareil (Firebase) à la connexion,
 * suppression à la déconnexion.
 */
final class DeviceTokensController
{
    public function store(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'token' => ['required', 'string', 'max:512'],
            'platform' => ['required', Rule::in(['android', 'ios', 'web'])],
        ]);

        // Un appareil passé à un autre compte change de propriétaire.
        DeviceToken::query()->updateOrCreate(['token' => $data['token']], [
            'user_id' => $user->id,
            'platform' => $data['platform'],
            'last_seen_at' => now(),
        ]);

        return response()->noContent();
    }

    public function destroy(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate(['token' => ['required', 'string', 'max:512']]);

        DeviceToken::query()->where('user_id', $user->id)->where('token', $data['token'])->delete();

        return response()->noContent();
    }
}
