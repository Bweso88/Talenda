<?php

namespace App\Filament\School\Resources\Assessments\Pages;

use App\Filament\School\Resources\Assessments\AssessmentResource;
use App\Models\User;
use App\Modules\Academic\Models\ClassSubject;
use App\Modules\Assessments\Policies\AssessmentPolicy;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateAssessment extends CreateRecord
{
    protected static string $resource = AssessmentResource::class;

    protected static ?string $title = 'Nouvelle évaluation';

    /**
     * La liste ne propose que les matières de l'enseignant ; on le revérifie
     * côté serveur.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        /** @var User $user */
        $user = Filament::auth()->user();

        abort_unless(app(AssessmentPolicy::class)->canGrade($user, ClassSubject::query()->findOrFail($data['class_subject_id'])), 403);

        return [...$data, 'created_by' => $user->id];
    }

    protected function getRedirectUrl(): string
    {
        return AssessmentResource::getUrl('scores', ['record' => $this->getRecord()]);
    }
}
