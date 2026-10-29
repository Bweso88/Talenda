<?php

declare(strict_types=1);

namespace App\Modules\SchoolLife\Models;

use App\Modules\Core\Tenancy\BelongsToTenant;
use App\Modules\Enrollments\Models\Enrollment;
use App\Modules\SchoolLife\Enums\AttendanceStatus;
use App\Modules\SchoolLife\Enums\JustificationStatus;
use App\Modules\SchoolLife\Policies\AttendancePolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * Absence ou retard d'un élève lors d'un appel.
 *
 * @property int $id
 * @property int $attendance_session_id
 * @property int $enrollment_id
 * @property AttendanceStatus $status
 * @property int|null $late_minutes
 * @property string|null $note
 * @property bool|null $is_justified chargé par selectJustified()
 * @property AttendanceSession $session
 * @property Enrollment $enrollment
 */
#[UsePolicy(AttendancePolicy::class)]
#[Fillable(['attendance_session_id', 'enrollment_id', 'status', 'late_minutes', 'note'])]
class AttendanceRecord extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'status' => AttendanceStatus::class,
            'is_justified' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<AttendanceSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(AttendanceSession::class, 'attendance_session_id');
    }

    /**
     * @return BelongsTo<Enrollment, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /**
     * Ajoute la colonne calculée `is_justified` : un justificatif accepté
     * de l'élève couvre la date de l'appel.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function selectJustified(Builder $query): Builder
    {
        return $query->select('attendance_records.*')->selectRaw('EXISTS ('.self::justifiedSql().') AS is_justified');
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function whereJustified(Builder $query, bool $justified = true): Builder
    {
        return $query->whereRaw(($justified ? '' : 'NOT ').'EXISTS ('.self::justifiedSql().')');
    }

    /**
     * Sous-requête corrélée sur attendance_records.
     */
    public static function justifiedSql(): string
    {
        $accepted = DB::getPdo()->quote(JustificationStatus::Accepted->value);

        return 'SELECT 1 FROM absence_justifications aj
            JOIN attendance_sessions s ON s.id = attendance_records.attendance_session_id
            WHERE aj.enrollment_id = attendance_records.enrollment_id
            AND aj.status = '.$accepted.'
            AND s.held_on BETWEEN aj.starts_on AND aj.ends_on';
    }
}
