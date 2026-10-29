<?php

namespace App\Filament\School\Resources\Students\Pages;

use App\Filament\School\Resources\Students\StudentResource;
use App\Modules\Enrollments\Models\Student;
use Filament\Resources\Pages\EditRecord;

class EditStudent extends EditRecord
{
    protected static string $resource = StudentResource::class;

    public function getTitle(): string
    {
        /** @var Student $student */
        $student = $this->getRecord();

        return $student->full_name;
    }

    public function getSubheading(): ?string
    {
        /** @var Student $student */
        $student = $this->getRecord();
        $enrollment = $student->activeEnrollment()->with('schoolClass')->first();

        return "Matricule {$student->registration_number}"
            .($enrollment === null ? ' — pas d\'inscription en cours' : " — {$enrollment->schoolClass->name}, {$enrollment->status->getLabel()}");
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
