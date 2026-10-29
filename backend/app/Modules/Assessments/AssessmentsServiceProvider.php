<?php

declare(strict_types=1);

namespace App\Modules\Assessments;

use App\Modules\Assessments\Actions\SetUpGrading;
use App\Modules\Core\Events\TenantProvisioned;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Branche le module Évaluations sur les autres modules par événements.
 */
final class AssessmentsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(TenantProvisioned::class, fn (TenantProvisioned $event) => app(SetUpGrading::class)->handle($event->tenant));
    }
}
