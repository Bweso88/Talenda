<?php

declare(strict_types=1);

namespace App\Modules\Academic\Actions;

use App\Modules\Academic\Enums\SchoolYearStatus;
use App\Modules\Academic\Models\SchoolYear;
use App\Modules\Core\Actions\RecordAudit;
use App\Modules\Core\Exceptions\BusinessRuleViolation;
use Illuminate\Database\Eloquent\Model;

/**
 * Ouverture et clôture d'une année scolaire (docs/PRODUCT_ARCHITECTURE.md §9).
 *
 * - une seule année ouverte à la fois (vérifié ici pour un message clair,
 *   et garanti par l'index unique partiel en base) ;
 * - une année clôturée ne se rouvre pas.
 */
final class ChangeSchoolYearStatus
{
    public function __construct(private readonly RecordAudit $recordAudit) {}

    public function open(SchoolYear $schoolYear, ?Model $actor = null): void
    {
        if ($schoolYear->status !== SchoolYearStatus::Draft) {
            throw new BusinessRuleViolation('Seule une année en préparation peut être ouverte.');
        }

        $openYear = SchoolYear::query()->open()->first();

        if ($openYear !== null) {
            throw new BusinessRuleViolation("Clôturez d'abord l'année {$openYear->name} avant d'ouvrir {$schoolYear->name}.");
        }

        $this->transition($schoolYear, SchoolYearStatus::Open, 'school_year.opened', $actor);
    }

    public function close(SchoolYear $schoolYear, ?Model $actor = null): void
    {
        if (! $schoolYear->isOpen()) {
            throw new BusinessRuleViolation('Seule une année ouverte peut être clôturée.');
        }

        $this->transition($schoolYear, SchoolYearStatus::Closed, 'school_year.closed', $actor);
    }

    private function transition(SchoolYear $schoolYear, SchoolYearStatus $status, string $auditAction, ?Model $actor): void
    {
        $schoolYear->update(['status' => $status]);

        $this->recordAudit->handle($auditAction, actor: $actor, subject: $schoolYear);
    }
}
