<?php

namespace App\Filament\Support;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Avatars générés localement (initiales sur fond coloré), au lieu du
 * service externe ui-avatars.com par défaut : aucune requête vers un tiers,
 * et rien à charger sur une connexion lente.
 */
final class InitialsAvatarProvider implements AvatarProvider
{
    public function get(Model $record): string
    {
        $name = Filament::getNameForDefaultAvatar($record);

        $initials = Str::of($name)
            ->trim()
            ->explode(' ')
            ->filter()
            ->take(2)
            ->map(fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)))
            ->implode('');

        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 64 64"><rect width="64" height="64" fill="#1e5aa8"/><text x="50%%" y="50%%" dy=".35em" text-anchor="middle" font-family="Arial, sans-serif" font-size="26" fill="#ffffff">%s</text></svg>',
            htmlspecialchars($initials, ENT_XML1),
        );

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
