<?php

use App\Modules\Core\Http\Controllers\Api\V1\AuthController;
use App\Modules\Core\Http\Controllers\Api\V1\CurrentTenantController;
use App\Modules\Core\Http\Controllers\Api\V1\MeController;
use App\Modules\Core\Http\Middleware\ResolveApiTenant;
use Illuminate\Support\Facades\Route;

/*
 * API v1 de l'app mobile (docs/PRODUCT_ARCHITECTURE.md §10).
 * Les routes d'une école passent par ResolveApiTenant (en-tête X-Tenant).
 */
Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:login')
        ->name('auth.login');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('me', MeController::class)->name('me');

        Route::middleware(ResolveApiTenant::class)->group(function (): void {
            Route::get('tenant', CurrentTenantController::class)->name('tenant');
        });
    });
});
