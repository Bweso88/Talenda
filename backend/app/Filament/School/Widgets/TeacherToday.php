<?php

namespace App\Filament\School\Widgets;

use App\Filament\School\Pages\TakeAttendancePage;
use App\Filament\School\Resources\Assessments\AssessmentResource;
use App\Models\User;
use App\Modules\Academic\Models\SchoolClass;
use App\Modules\Core\Support\SchoolClock;
use App\Modules\SchoolLife\Models\AttendanceSession;
use App\Modules\SchoolLife\Models\AttendanceSlot;
use App\Modules\SchoolLife\Support\AttendanceClasses;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

/**
 * Pour l'enseignant : ses classes du jour et l'état de l'appel.
 */
class TeacherToday extends Widget
{
    protected static ?int $sort = -4;

    protected string $view = 'filament.school.widgets.teacher-today';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $user = Filament::auth()->user();

        return ($user?->can('attendance.take') ?? false) && ! $user->can('attendance.manage');
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        /** @var User $user */
        $user = Filament::auth()->user();
        $slots = AttendanceSlot::query()->orderBy('position')->get();
        $done = AttendanceSession::query()
            ->whereDate('held_on', SchoolClock::today())
            ->get(['school_class_id', 'attendance_slot_id'])
            ->map(fn (AttendanceSession $session): string => "{$session->school_class_id}-{$session->attendance_slot_id}")
            ->all();

        return [
            'classes' => AttendanceClasses::for($user)->orderBy('name')->get()->map(fn (SchoolClass $schoolClass): array => [
                'name' => $schoolClass->name,
                'slots' => $slots->map(fn (AttendanceSlot $slot): array => [
                    'name' => $slot->name,
                    'done' => in_array("{$schoolClass->id}-{$slot->id}", $done, true),
                ])->all(),
            ])->all(),
            'attendanceUrl' => TakeAttendancePage::getUrl(),
            'gradesUrl' => $user->can('assessment.enter') ? AssessmentResource::getUrl() : null,
        ];
    }
}
