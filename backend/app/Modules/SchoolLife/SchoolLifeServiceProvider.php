<?php

declare(strict_types=1);

namespace App\Modules\SchoolLife;

use App\Modules\Core\Events\TenantProvisioned;
use App\Modules\SchoolLife\Actions\SetUpSchoolLife;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Branche le module Vie scolaire sur les autres modules par événements.
 */
final class SchoolLifeServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(TenantProvisioned::class, fn (TenantProvisioned $event) => app(SetUpSchoolLife::class)->handle($event->tenant));
    }
}
