<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Http\Controllers;

use App\Modules\Academic\Models\ClassSubject;
use App\Modules\Academic\Models\SchoolClass;
use App\Modules\Academic\Models\SchoolYear;
use App\Modules\Academic\Models\StaffMember;
use App\Modules\Academic\Models\Subject;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Enrollments\Enums\EnrollmentStatus;
use App\Modules\Enrollments\Models\Enrollment;
use App\Modules\Enrollments\Models\Guardian;
use App\Modules\Enrollments\Models\StudentGuardian;
use Illuminate\Http\JsonResponse;

/**
 * Photographie de l'école pour l'année ouverte : ce qu'une application
 * partenaire (Scolia) recopie chez elle. Talenda reste la référence ;
 * le partenaire rapproche par les uuid et désactive ce qui a disparu.
 */
final class SnapshotController
{
    public function __invoke(TenantContext $tenantContext): JsonResponse
    {
        $tenant = $tenantContext->get();
        $year = SchoolYear::query()->open()->first();

        $classes = $year === null ? collect() : SchoolClass::query()
            ->where('school_year_id', $year->id)
            ->with(['level', 'stream', 'headTeacher'])
            ->orderBy('name')
            ->get();

        $enrollments = $year === null ? collect() : Enrollment::query()
            ->where('school_year_id', $year->id)
            ->where('status', EnrollmentStatus::Enrolled)
            ->with(['student', 'schoolClass'])
            ->get();

        $studentIds = $enrollments->pluck('student_id')->all();

        return response()->json([
            'generated_at' => now()->toIso8601String(),
            'school' => [
                'uuid' => $tenant?->uuid,
                'name' => $tenant?->name,
                'timezone' => $tenant?->timezone,
            ],
            'school_year' => $year === null ? null : [
                'uuid' => $year->uuid,
                'name' => $year->name,
                'starts_on' => $year->starts_on->toDateString(),
                'ends_on' => $year->ends_on->toDateString(),
            ],
            'subjects' => Subject::query()->orderBy('name')->get()->map(fn (Subject $subject): array => [
                'uuid' => $subject->uuid,
                'code' => $subject->code,
                'name' => $subject->name,
            ])->all(),
            'classes' => $classes->map(fn (SchoolClass $class): array => [
                'uuid' => $class->uuid,
                'name' => $class->name,
                'level' => $class->level->name,
                'level_code' => $class->level->code,
                'stream' => $class->stream?->code,
                'head_teacher_uuid' => $class->headTeacher?->uuid,
            ])->all(),
            'staff' => StaffMember::query()->with('membership.user')->orderBy('last_name')->get()->map(fn (StaffMember $staff): array => [
                'uuid' => $staff->uuid,
                'first_name' => $staff->first_name,
                'last_name' => $staff->last_name,
                'full_name' => $staff->full_name,
                'position' => $staff->position->value,
                'phone' => $staff->phone,
                'email' => $staff->email,
                'is_active' => $staff->is_active,
                'account_uuid' => $staff->membership?->user?->uuid,
            ])->all(),
            'teaching' => ClassSubject::query()
                ->whereIn('school_class_id', $classes->pluck('id')->all())
                ->whereNotNull('teacher_id')
                ->with(['schoolClass', 'subject', 'teacher'])
                ->get()
                ->map(fn (ClassSubject $classSubject): array => [
                    'class_uuid' => $classSubject->schoolClass->uuid,
                    'subject_uuid' => $classSubject->subject->uuid,
                    'teacher_uuid' => $classSubject->teacher?->uuid,
                    'coefficient' => (float) $classSubject->coefficient,
                ])->all(),
            'students' => $enrollments->sortBy(fn (Enrollment $enrollment): string => $enrollment->student->last_name.' '.$enrollment->student->first_names)->values()->map(fn (Enrollment $enrollment): array => [
                'uuid' => $enrollment->student->uuid,
                'registration_number' => $enrollment->student->registration_number,
                'last_name' => $enrollment->student->last_name,
                'first_names' => $enrollment->student->first_names,
                'gender' => $enrollment->student->gender->value,
                'birth_date' => $enrollment->student->birth_date->toDateString(),
                'class_uuid' => $enrollment->schoolClass->uuid,
            ])->all(),
            'guardians' => StudentGuardian::query()
                ->whereIn('student_id', $studentIds)
                ->with(['student', 'guardian.user'])
                ->get()
                ->groupBy('guardian_id')
                ->map(function ($links): array {
                    /** @var Guardian $guardian */
                    $guardian = $links->first()->guardian;

                    return [
                        'uuid' => $guardian->uuid,
                        'last_name' => $guardian->last_name,
                        'first_names' => $guardian->first_names,
                        'full_name' => $guardian->full_name,
                        'phone' => $guardian->phone,
                        'email' => $guardian->email,
                        // Compte Talenda du parent (app parents ouverte par l'école), sinon null.
                        'account_uuid' => $guardian->user?->uuid,
                        'children' => $links->map(fn (StudentGuardian $link): array => [
                            'student_uuid' => $link->student->uuid,
                            'relationship' => $link->relationship->value,
                            'is_primary_contact' => $link->is_primary_contact,
                        ])->values()->all(),
                    ];
                })
                ->sortBy('last_name')
                ->values()
                ->all(),
        ]);
    }
}
