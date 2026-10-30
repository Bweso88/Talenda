<?php

namespace App\Filament\Support;

use Filament\Panel;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\HtmlString;

/**
 * Thème Talenda commun aux back-offices : feuille de style simple
 * (public/css/talenda-admin.css), sans étape de compilation.
 */
final class TalendaTheme
{
    public static function apply(Panel $panel): Panel
    {
        return $panel
            ->darkMode(false)
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): HtmlString => new HtmlString(
                '<link rel="stylesheet" href="'.e(asset('css/talenda-admin.css')).'?v='.@filemtime(public_path('css/talenda-admin.css')).'">',
            ));
    }
}
