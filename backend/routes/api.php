<?php

use App\Modules\Communication\Http\Controllers\DeviceTokensController;
use App\Modules\Communication\Http\Controllers\NotificationsController;
use App\Modules\Core\Http\Controllers\Api\V1\AuthController;
use App\Modules\Core\Http\Controllers\Api\V1\CurrentTenantController;
use App\Modules\Core\Http\Controllers\Api\V1\MeController;
use App\Modules\Core\Http\Middleware\ResolveApiTenant;
use App\Modules\ParentPortal\Http\Controllers\ChildAttendanceController;
use App\Modules\ParentPortal\Http\Controllers\ChildFinancesController;
use App\Modules\ParentPortal\Http\Controllers\ChildrenController;
use App\Modules\ParentPortal\Http\Controllers\ChildResultsController;
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
        Route::post('auth/password', [AuthController::class, 'changePassword'])->name('auth.password');
        Route::post('device-tokens', [DeviceTokensController::class, 'store'])->name('device-tokens.store');
        Route::delete('device-tokens', [DeviceTokensController::class, 'destroy'])->name('device-tokens.destroy');
        Route::get('me', MeController::class)->name('me');

        Route::middleware(ResolveApiTenant::class)->group(function (): void {
            Route::get('tenant', CurrentTenantController::class)->name('tenant');

            Route::get('notifications', [NotificationsController::class, 'index'])->name('notifications.index');
            Route::post('notifications/read-all', [NotificationsController::class, 'readAll'])->name('notifications.read-all');
            Route::post('notifications/{notification}/read', [NotificationsController::class, 'read'])->name('notifications.read');

            // Portail parents : uniquement les enfants dont le compte est
            // responsable (introuvable sinon).
            Route::prefix('children')->name('children.')->group(function (): void {
                Route::get('/', [ChildrenController::class, 'index'])->name('index');
                Route::get('{child}', [ChildrenController::class, 'show'])->name('show');
                Route::get('{child}/finances', [ChildFinancesController::class, 'index'])->name('finances');
                Route::get('{child}/payments/{payment}/receipt', [ChildFinancesController::class, 'receipt'])->name('receipt');
                Route::get('{child}/results', [ChildResultsController::class, 'index'])->name('results.index');
                Route::get('{child}/results/{result}', [ChildResultsController::class, 'show'])->name('results.show');
                Route::get('{child}/results/{result}/report-card', [ChildResultsController::class, 'reportCard'])->name('results.report-card');
                Route::get('{child}/attendance', [ChildAttendanceController::class, 'index'])->name('attendance');
                Route::post('{child}/absence-justifications', [ChildAttendanceController::class, 'storeJustification'])
                    ->middleware('throttle:20,60')
                    ->name('absence-justifications.store');
            });
        });
    });
});
