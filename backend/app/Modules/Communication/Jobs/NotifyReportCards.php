<?php

declare(strict_types=1);

namespace App\Modules\Communication\Jobs;

use App\Modules\Academic\Models\Term;
use App\Modules\Assessments\Models\TermResult;
use App\Modules\Communication\Enums\NotificationType;
use App\Modules\Communication\Enums\SmsPurpose;
use App\Modules\Communication\Models\CommunicationSetting;
use App\Modules\Communication\Support\FamilyContacts;
use App\Modules\Communication\Support\ParentNotifier;
use App\Modules\Communication\Support\Wording;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Prévient chaque famille que le bulletin de son enfant est publié (en
 * file d'attente : une école peut compter des centaines d'élèves).
 */
final class NotifyReportCards implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $tenantId, public readonly int $termId) {}

    public function handle(TenantContext $tenantContext, ParentNotifier $notifier): void
    {
        $tenantContext->run(Tenant::query()->findOrFail($this->tenantId), function () use ($notifier): void {
            $term = Term::query()->findOrFail($this->termId);
            $withSms = CommunicationSetting::current()->sms_on_report_card;
            $school = FamilyContacts::schoolName();

            TermResult::query()
                ->where('term_id', $term->id)
                ->with('enrollment.student.guardians')
                ->lazyById(200)
                ->each(function (TermResult $result) use ($notifier, $term, $withSms, $school): void {
                    $student = $result->enrollment->student;
                    $name = Wording::firstName($student);
                    $average = $result->average === null ? null : number_format((float) $result->average, 2, ',', '').'/20';
                    $summary = $average === null ? '' : " Moyenne : {$average}".($result->rank === null ? '' : ", rang {$result->rankLabel()}").'.';

                    $notifier->notify(
                        $student,
                        NotificationType::ReportCard,
                        "Bulletin du {$term->name}",
                        'Le bulletin '.Wording::of($name)." est disponible.{$summary}",
                        ['tab' => 'results', 'result' => $result->uuid],
                        sms: $withSms ? "{$school} : le bulletin du {$term->name} ".Wording::of($name)." est disponible.{$summary}" : null,
                        purpose: SmsPurpose::ReportCard,
                        subject: $result,
                    );
                });
        });
    }
}
