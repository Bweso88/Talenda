<?php

namespace App\Filament\School\Resources\Students\Pages;

use App\Filament\School\Resources\Students\StudentResource;
use App\Modules\Academic\Models\SchoolClass;
use App\Modules\Core\Exceptions\BusinessRuleViolation;
use App\Modules\Enrollments\Actions\EnrollNewStudent;
use App\Modules\Enrollments\Enums\EnrollmentStatus;
use App\Modules\Enrollments\Models\Student;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

/**
 * Nouvelle inscription : élève + responsables + inscription, via
 * l'action métier EnrollNewStudent.
 */
class CreateStudent extends CreateRecord
{
    protected static string $resource = StudentResource::class;

    protected static ?string $title = 'Nouvelle inscription';

    protected static bool $canCreateAnother = false;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $studentData = collect($data)
            ->only(['last_name', 'first_names', 'gender', 'birth_date', 'birth_place', 'nationality', 'address', 'previous_school', 'medical_notes', 'photo_path'])
            ->all();

        try {
            $enrollment = app(EnrollNewStudent::class)->handle(
                $studentData,
                array_values($data['guardians'] ?? []),
                SchoolClass::query()->with('schoolYear')->findOrFail($data['school_class_id']),
                EnrollmentStatus::from($data['status']),
                array_values($data['documents'] ?? []),
                (bool) ($data['allow_possible_duplicate'] ?? false),
                Filament::auth()->user(),
            );
        } catch (BusinessRuleViolation $exception) {
            Notification::make()->danger()->title($exception->getMessage())->persistent()->send();

            throw new Halt;
        }

        return $enrollment->student;
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label('Inscrire');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        /** @var Student $student */
        $student = $this->getRecord();

        return "{$student->full_name} est inscrit(e) — matricule {$student->registration_number}.";
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
