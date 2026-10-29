<?php

declare(strict_types=1);

namespace App\Modules\ParentPortal\Http\Controllers;

use App\Models\User;
use App\Modules\Core\Exceptions\BusinessRuleViolation;
use App\Modules\ParentPortal\Http\Requests\SubmitAbsenceJustificationRequest;
use App\Modules\ParentPortal\Http\Resources\AbsenceJustificationResource;
use App\Modules\ParentPortal\Http\Resources\AttendanceTotalResource;
use App\Modules\ParentPortal\Support\ChildSummary;
use App\Modules\ParentPortal\Support\GuardianChildren;
use App\Modules\SchoolLife\Actions\JustifyAbsences;
use App\Modules\SchoolLife\Enums\AttendanceStatus;
use App\Modules\SchoolLife\Enums\JustificationReason;
use App\Modules\SchoolLife\Models\AbsenceJustification;
use App\Modules\SchoolLife\Models\AttendanceRecord;
use App\Modules\SchoolLife\Models\AttendanceSession;
use App\Modules\SchoolLife\Models\DisciplinaryIncident;
use App\Modules\SchoolLife\Support\AttendanceTotal;
use App\Modules\SchoolLife\Support\AttendanceTotals;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Vie scolaire de l'année en cours : absences, retards, justificatifs,
 * discipline ; dépôt d'un justificatif par le parent.
 */
final class ChildAttendanceController
{
    public function index(Request $request, string $child): JsonResponse
    {
        $student = GuardianChildren::findOrFail($this->user($request), $child);
        $enrollment = ChildSummary::latestEnrollment($student);

        if ($enrollment === null) {
            return response()->json(['data' => null]);
        }

        $year = $enrollment->schoolYear;
        $records = AttendanceRecord::selectJustified(AttendanceRecord::query())
            ->where('enrollment_id', $enrollment->id)
            ->with('session.slot')
            ->orderByDesc(AttendanceSession::query()->select('held_on')->whereColumn('attendance_sessions.id', 'attendance_records.attendance_session_id'))
            ->limit(200)
            ->get();

        return response()->json(['data' => [
            'school_year' => $year->name,
            'totals' => AttendanceTotalResource::make(AttendanceTotals::between([$enrollment->id], $year->starts_on, $year->ends_on)[$enrollment->id] ?? new AttendanceTotal),
            'records' => $records->map(fn (AttendanceRecord $record): array => [
                'date' => $record->session->held_on->toDateString(),
                'slot' => $record->session->slot->name,
                'status' => $record->status->value,
                'status_label' => $record->status->getLabel(),
                'hours' => $record->status === AttendanceStatus::Absent ? AttendanceTotal::hours($record->session->duration_minutes) : null,
                'late_minutes' => $record->late_minutes,
                'is_justified' => $record->status === AttendanceStatus::Absent ? (bool) $record->is_justified : null,
            ])->all(),
            'justifications' => AbsenceJustificationResource::collection(
                AbsenceJustification::query()->where('enrollment_id', $enrollment->id)->latest('starts_on')->get(),
            ),
            'incidents' => DisciplinaryIncident::query()->where('enrollment_id', $enrollment->id)->latest('occurred_on')->get()
                ->map(fn (DisciplinaryIncident $incident): array => [
                    'date' => $incident->occurred_on->toDateString(),
                    'description' => $incident->description,
                    'sanction' => $incident->sanction->value,
                    'sanction_label' => $incident->sanction->getLabel(),
                    'suspension_days' => $incident->suspension_days,
                ])->all(),
        ]]);
    }

    /**
     * Le parent dépose un justificatif : la vie scolaire l'examinera.
     */
    public function storeJustification(SubmitAbsenceJustificationRequest $request, string $child, JustifyAbsences $justifyAbsences): JsonResponse
    {
        $user = $this->user($request);
        $student = GuardianChildren::findOrFail($user, $child);
        $enrollment = ChildSummary::latestEnrollment($student);

        if ($enrollment === null) {
            throw ValidationException::withMessages(['starts_on' => 'Cet enfant n\'est inscrit dans aucune classe.']);
        }

        try {
            $justification = $justifyAbsences->submitByGuardian(
                $enrollment,
                Carbon::parse($request->string('starts_on')->toString()),
                Carbon::parse($request->string('ends_on')->toString()),
                JustificationReason::from($request->string('reason')->toString()),
                $request->filled('comment') ? $request->string('comment')->toString() : null,
                $user,
            );
        } catch (BusinessRuleViolation $exception) {
            throw ValidationException::withMessages(['starts_on' => $exception->getMessage()]);
        }

        return (new AbsenceJustificationResource($justification))->response()->setStatusCode(201);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
