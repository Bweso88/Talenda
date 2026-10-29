<?php

namespace App\Providers;

use App\Modules\Core\Tenancy\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Une école active par requête, job ou commande (voir TenantContext).
        $this->app->scoped(TenantContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $isProduction = $this->app->isProduction();

        // En développement : erreur immédiate sur un chargement paresseux
        // (N+1), un attribut inexistant ou une affectation de masse ignorée.
        Model::shouldBeStrict(! $isProduction);

        DB::prohibitDestructiveCommands($isProduction);

        Password::defaults(fn () => $isProduction
            ? Password::min(10)->letters()->numbers()->uncompromised()
            : Password::min(10));

        // Connexion : limitée par identifiant ET par adresse IP
        // (docs/PRODUCT_ARCHITECTURE.md §14).
        RateLimiter::for('login', fn (Request $request): array => [
            Limit::perMinute(5)->by('login:'.mb_strtolower((string) $request->input('login'))),
            Limit::perMinute(20)->by('login-ip:'.$request->ip()),
        ]);
    }
}
