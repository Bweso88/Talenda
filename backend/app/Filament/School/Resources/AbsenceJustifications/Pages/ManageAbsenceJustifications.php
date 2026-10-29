<?php

namespace App\Filament\School\Resources\AbsenceJustifications\Pages;

use App\Filament\School\Resources\AbsenceJustifications\AbsenceJustificationResource;
use App\Models\User;
use App\Modules\Core\Exceptions\BusinessRuleViolation;
use App\Modules\Enrollments\Models\Enrollment;
use App\Modules\SchoolLife\Actions\JustifyAbsences;
use App\Modules\SchoolLife\Enums\JustificationReason;
use App\Modules\SchoolLife\Models\AbsenceJustification;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Carbon;

class ManageAbsenceJustifications extends ManageRecords
{
    protected static string $resource = AbsenceJustificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nouveau justificatif')
                ->createAnother(false)
                ->successNotification(null)
                ->using(function (array $data): AbsenceJustification {
                    /** @var User $user */
                    $user = Filament::auth()->user();

                    try {
                        $justification = app(JustifyAbsences::class)->handle(
                            Enrollment::query()->findOrFail($data['enrollment_id']),
                            Carbon::parse($data['starts_on']),
                            Carbon::parse($data['ends_on']),
                            $data['reason'] instanceof JustificationReason ? $data['reason'] : JustificationReason::from($data['reason']),
                            $data['comment'] ?? null,
                            $user,
                        );
                    } catch (BusinessRuleViolation $exception) {
                        Notification::make()->danger()->title($exception->getMessage())->send();

                        throw new Halt;
                    }

                    $covered = JustifyAbsences::coveredAbsences($justification);
                    Notification::make()->success()->title("Justificatif enregistré : {$covered} absence(s) déjà relevée(s) justifiée(s).")->send();

                    return $justification;
                }),
        ];
    }
}
