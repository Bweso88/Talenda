<?php

declare(strict_types=1);

namespace App\Modules\SchoolLife\Support;

use App\Modules\SchoolLife\Enums\AttendanceStatus;
use App\Modules\SchoolLife\Models\AttendanceRecord;
use Illuminate\Support\Carbon;

/**
 * Cumul des absences (en minutes, dont justifiées) et des retards, par
 * inscription, entre deux dates incluses.
 */
final class AttendanceTotals
{
    /**
     * @param  list<int>  $enrollmentIds
     * @return array<int, AttendanceTotal> par id d'inscription (élèves sans absence ni retard absents du tableau)
     */
    public static function between(array $enrollmentIds, Carbon $from, Carbon $to): array
    {
        if ($enrollmentIds === []) {
            return [];
        }

        $rows = AttendanceRecord::query()
            ->join('attendance_sessions', 'attendance_sessions.id', '=', 'attendance_records.attendance_session_id')
            ->whereIn('attendance_records.enrollment_id', $enrollmentIds)
            ->whereBetween('attendance_sessions.held_on', [$from->toDateString(), $to->toDateString()])
            ->groupBy('attendance_records.enrollment_id')
            ->selectRaw('attendance_records.enrollment_id')
            ->selectRaw('COALESCE(SUM(attendance_sessions.duration_minutes) FILTER (WHERE attendance_records.status = ?), 0) AS absence_minutes', [AttendanceStatus::Absent->value])
            ->selectRaw('COALESCE(SUM(attendance_sessions.duration_minutes) FILTER (WHERE attendance_records.status = ? AND EXISTS ('.AttendanceRecord::justifiedSql().')), 0) AS justified_minutes', [AttendanceStatus::Absent->value])
            ->selectRaw('COUNT(*) FILTER (WHERE attendance_records.status = ?) AS late_count', [AttendanceStatus::Late->value])
            ->toBase()
            ->get();

        $totals = [];

        foreach ($rows as $row) {
            $totals[(int) $row->enrollment_id] = new AttendanceTotal(
                absenceMinutes: (int) $row->absence_minutes,
                justifiedAbsenceMinutes: (int) $row->justified_minutes,
                lateCount: (int) $row->late_count,
            );
        }

        return $totals;
    }
}
