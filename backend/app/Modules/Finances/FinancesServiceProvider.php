<?php

declare(strict_types=1);

namespace App\Modules\Finances;

use App\Modules\Academic\Events\NextSchoolYearPrepared;
use App\Modules\Academic\Events\SchoolYearCreated;
use App\Modules\Core\Events\TenantProvisioned;
use App\Modules\Enrollments\Events\StudentEnrolled;
use App\Modules\Finances\Actions\CarryOverFinances;
use App\Modules\Finances\Actions\CreateEnrollmentInvoice;
use App\Modules\Finances\Actions\SetUpFinances;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Branche le module Finances sur les événements des autres modules : les
 * modules Inscriptions et Structure scolaire ne connaissent pas Finances.
 * Les écouteurs sont synchrones : ils s'exécutent dans la même transaction
 * que l'action qui les déclenche (pas d'inscription sans sa facture).
 */
final class FinancesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(TenantProvisioned::class, fn (TenantProvisioned $event) => app(SetUpFinances::class)->forNewTenant($event->tenant));

        Event::listen(SchoolYearCreated::class, fn (SchoolYearCreated $event) => app(SetUpFinances::class)->paymentPlansFor($event->schoolYear));

        Event::listen(NextSchoolYearPrepared::class, fn (NextSchoolYearPrepared $event) => app(CarryOverFinances::class)->handle($event->previous, $event->next));

        Event::listen(StudentEnrolled::class, fn (StudentEnrolled $event) => app(CreateEnrollmentInvoice::class)->handle($event->enrollment));
    }
}
