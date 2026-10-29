<?php

namespace App\Providers\Filament;

use App\Filament\School\Auth\Login;
use App\Filament\School\Pages\EditSchoolProfile;
use App\Filament\School\Support\NavigationGroups;
use App\Filament\Support\BrandColors;
use App\Filament\Support\InitialsAvatarProvider;
use App\Modules\Core\Http\Middleware\SyncFilamentTenant;
use App\Modules\Core\Models\Tenant;
use App\Modules\Finances\Http\Controllers\ReceiptController;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class SchoolPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('school')
            ->path('ecole')
            ->brandName('Talenda')
            ->brandLogo(fn () => asset('images/talenda-logo-horizontal.png'))
            ->brandLogoHeight('2.25rem')
            ->favicon(fn () => asset('favicon.png'))
            ->login(Login::class)
            ->tenant(Tenant::class, slugAttribute: 'slug')
            ->tenantProfile(EditSchoolProfile::class)
            ->authenticatedTenantRoutes(function (): void {
                Route::get('recus/{payment}', ReceiptController::class)->name('receipt');
            })
            ->navigationGroups([
                NavigationGroups::STUDENTS,
                NavigationGroups::GRADES,
                NavigationGroups::SCHOOL_LIFE,
                NavigationGroups::COMMUNICATION,
                NavigationGroups::FINANCES,
                NavigationGroups::SCHOOL_YEAR,
                NavigationGroups::STAFF,
                NavigationGroups::SETTINGS,
                NavigationGroups::FINANCE_SETTINGS,
            ])
            ->tenantMiddleware([
                SyncFilamentTenant::class,
            ], isPersistent: true)
            ->defaultAvatarProvider(InitialsAvatarProvider::class)
            ->colors([
                // Bleu du logo Talenda.
                'primary' => BrandColors::primary(),
            ])
            ->discoverResources(in: app_path('Filament/School/Resources'), for: 'App\Filament\School\Resources')
            ->discoverPages(in: app_path('Filament/School/Pages'), for: 'App\Filament\School\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/School/Widgets'), for: 'App\Filament\School\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
