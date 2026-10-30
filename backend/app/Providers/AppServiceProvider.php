<?php

namespace App\Providers;

use App\Filament\School\Support\DashboardFigures;
use App\Modules\Core\Http\Middleware\ResolveApiTenant;
use App\Modules\Core\Tenancy\TenantContext;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Schema as GeneratorSchema;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\RouteInfo;
use Filament\Forms\Components\DatePicker;
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
        $this->app->scoped(DashboardFigures::class);
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

        $this->configureApiDocumentation();

        Password::defaults(fn () => $isProduction
            ? Password::min(10)->letters()->numbers()->uncompromised()
            : Password::min(10));

        // Dates toujours saisies et affichées en JJ/MM/AAAA, quelle que soit
        // la langue du navigateur (le sélecteur natif suit celle-ci).
        DatePicker::configureUsing(fn (DatePicker $datePicker) => $datePicker
            ->native(false)
            ->displayFormat('d/m/Y')
            ->firstDayOfWeek(1));

        // Connexion : limitée par identifiant ET par adresse IP
        // (docs/PRODUCT_ARCHITECTURE.md §14).
        RateLimiter::for('login', fn (Request $request): array => [
            Limit::perMinute(5)->by('login:'.mb_strtolower((string) $request->input('login'))),
            Limit::perMinute(20)->by('login-ip:'.$request->ip()),
        ]);

        // Vérification d'identifiants par une application partenaire : tous
        // les parents arrivent de l'adresse IP de son serveur, la limite se
        // fait donc par identifiant et par clé d'intégration.
        RateLimiter::for('integration-login', fn (Request $request): array => [
            Limit::perMinute(5)->by('integration-login:'.mb_strtolower((string) $request->input('login'))),
            Limit::perMinute(300)->by('integration-client:'.hash('sha256', (string) $request->bearerToken())),
        ]);
    }

    /**
     * Documentation OpenAPI de l'API mobile (Scramble) : jeton Bearer et
     * en-tête X-Tenant sur les routes d'une école. C'est le contrat de
     * l'app Flutter (docs/openapi.json, régénéré par
     * `php artisan scramble:export --path=../docs/openapi.json`).
     */
    private function configureApiDocumentation(): void
    {
        Scramble::configure()
            ->withDocumentTransformers(fn (OpenApi $openApi) => $openApi->secure(SecurityScheme::http('bearer')))
            ->withOperationTransformers(function (Operation $operation, RouteInfo $routeInfo): void {
                if (! in_array(ResolveApiTenant::class, $routeInfo->route->gatherMiddleware(), true)) {
                    return;
                }

                $operation->addParameters([
                    Parameter::make(ResolveApiTenant::HEADER, 'header')
                        ->required(true)
                        ->description('uuid de l\'école (voir GET /v1/me).')
                        ->setSchema(GeneratorSchema::fromType(new StringType)),
                ]);
            });
    }
}
