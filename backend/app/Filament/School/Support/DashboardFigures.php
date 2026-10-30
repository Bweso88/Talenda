<?php

namespace App\Filament\School\Support;

use App\Modules\Academic\Enums\TermStatus;
use App\Modules\Academic\Models\Cycle;
use App\Modules\Academic\Models\SchoolClass;
use App\Modules\Academic\Models\SchoolYear;
use App\Modules\Academic\Models\Term;
use App\Modules\Communication\Enums\SmsStatus;
use App\Modules\Communication\Models\SmsMessage;
use App\Modules\Core\Support\SchoolClock;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Enrollments\Enums\EnrollmentStatus;
use App\Modules\Enrollments\Models\Enrollment;
use App\Modules\Finances\Models\InvoiceLine;
use App\Modules\Finances\Models\Payment;
use App\Modules\Finances\Support\UnpaidReport;
use App\Modules\Finances\Support\UnpaidRow;
use App\Modules\SchoolLife\Enums\AttendanceStatus;
use App\Modules\SchoolLife\Enums\JustificationStatus;
use App\Modules\SchoolLife\Models\AbsenceJustification;
use App\Modules\SchoolLife\Models\AttendanceRecord;
use App\Modules\SchoolLife\Models\AttendanceSession;
use App\Modules\SchoolLife\Models\AttendanceSlot;
use App\Modules\SchoolLife\Support\ClassRoll;
use Carbon\CarbonInterface;

/**
 * Chiffres du tableau de bord de l'école, pour l'année ouverte. Calculés à
 * la demande (une école compte quelques centaines d'élèves) et mémorisés
 * le temps d'une requête.
 */
final class DashboardFigures
{
    /**
     * @var array<string, mixed>
     */
    private array $memo = [];

    public function year(): ?SchoolYear
    {
        return $this->remember('year', fn () => SchoolYear::query()->open()->first());
    }

    public function currentTerm(): ?Term
    {
        return $this->remember('term', function (): ?Term {
            $year = $this->year();
            $today = SchoolClock::today();

            return $year === null ? null : Term::query()
                ->where('school_year_id', $year->id)
                ->orderBy('position')
                ->get()
                ->first(fn (Term $term): bool => $today->lte($term->ends_on)) ?? $year->terms()->orderByDesc('position')->first();
        });
    }

    public function enrolled(): int
    {
        return $this->remember('enrolled', fn (): int => $this->year() === null ? 0 : Enrollment::query()
            ->where('school_year_id', $this->year()->id)
            ->where('status', EnrollmentStatus::Enrolled)
            ->count());
    }

    public function preRegistered(): int
    {
        return $this->remember('pre', fn (): int => $this->year() === null ? 0 : Enrollment::query()
            ->where('school_year_id', $this->year()->id)
            ->where('status', EnrollmentStatus::PreRegistered)
            ->count());
    }

    /**
     * Élèves inscrits par cycle.
     *
     * @return list<array{name: string, count: int}>
     */
    public function byCycle(): array
    {
        return $this->remember('cycles', function (): array {
            $year = $this->year();

            if ($year === null) {
                return [];
            }

            $counts = Enrollment::query()
                ->join('school_classes', 'school_classes.id', '=', 'enrollments.school_class_id')
                ->join('levels', 'levels.id', '=', 'school_classes.level_id')
                ->where('enrollments.school_year_id', $year->id)
                ->where('enrollments.status', EnrollmentStatus::Enrolled)
                ->groupBy('levels.cycle_id')
                ->selectRaw('levels.cycle_id, count(*) as total')
                ->toBase()
                ->pluck('total', 'cycle_id');

            return Cycle::query()
                ->orderBy('position')
                ->get()
                ->filter(fn (Cycle $cycle): bool => ($counts[$cycle->id] ?? 0) > 0)
                ->map(fn (Cycle $cycle): array => ['name' => $cycle->name, 'count' => (int) $counts[$cycle->id]])
                ->values()
                ->all();
        });
    }

    /**
     * Présences du jour, sur les classes dont l'appel est fait.
     *
     * @return array{called: int, absent: int, late: int}
     */
    public function attendanceToday(): array
    {
        return $this->remember('attendance', function (): array {
            $today = SchoolClock::today();
            $sessions = AttendanceSession::query()->whereDate('held_on', $today)->get();

            if ($sessions->isEmpty()) {
                return ['called' => 0, 'absent' => 0, 'late' => 0];
            }

            $called = SchoolClass::query()
                ->whereIn('id', $sessions->pluck('school_class_id')->unique())
                ->get()
                ->sum(fn (SchoolClass $schoolClass): int => ClassRoll::on($schoolClass, $today)->count());

            $records = AttendanceRecord::query()->whereIn('attendance_session_id', $sessions->modelKeys())->get();

            return [
                'called' => (int) $called,
                'absent' => $records->where('status', AttendanceStatus::Absent)->pluck('enrollment_id')->unique()->count(),
                'late' => $records->where('status', AttendanceStatus::Late)->pluck('enrollment_id')->unique()->count(),
            ];
        });
    }

    /**
     * Appels pas encore faits aujourd'hui, pour les créneaux déjà commencés.
     */
    public function missingCalls(): int
    {
        return $this->remember('missing', function (): int {
            $year = $this->year();

            if ($year === null || SchoolClock::today()->isWeekend()) {
                return 0;
            }

            $now = now(app(TenantContext::class)->get()->timezone ?? config('app.timezone'))->format('H:i:s');
            $slots = AttendanceSlot::query()->where('starts_at', '<=', $now)->pluck('id');
            $classes = SchoolClass::query()->where('school_year_id', $year->id)->whereHas('enrollments', fn ($query) => $query->where('status', EnrollmentStatus::Enrolled))->count();
            $done = AttendanceSession::query()->whereDate('held_on', SchoolClock::today())->whereIn('attendance_slot_id', $slots)->count();

            return max(0, $slots->count() * $classes - $done);
        });
    }

    public function pendingJustifications(): int
    {
        return $this->remember('justifications', fn (): int => AbsenceJustification::query()->where('status', JustificationStatus::Pending)->count());
    }

    public function failedSms(): int
    {
        return $this->remember('sms', fn (): int => SmsMessage::query()->where('status', SmsStatus::Failed)->where('created_at', '>=', now()->subDays(7))->count());
    }

    public function lockedTerm(): ?Term
    {
        return $this->remember('locked', fn (): ?Term => $this->year() === null ? null : Term::query()
            ->where('school_year_id', $this->year()->id)
            ->where('status', TermStatus::Locked)
            ->orderBy('position')
            ->first());
    }

    /**
     * Facturé et encaissé sur l'année ouverte, encaissé ce mois-ci.
     *
     * @return array{billed: int, collected: int, month: int, today: int}
     */
    public function finance(): array
    {
        return $this->remember('finance', function (): array {
            $year = $this->year();
            $today = SchoolClock::today();
            $payments = Payment::query()->valid();

            return [
                'billed' => $year === null ? 0 : (int) InvoiceLine::query()->whereHas('invoice', fn ($query) => $query->where('school_year_id', $year->id))->sum('amount'),
                'collected' => $year === null ? 0 : (int) (clone $payments)->whereHas('invoice', fn ($query) => $query->where('school_year_id', $year->id))->sum('amount'),
                'month' => (int) (clone $payments)->where('paid_on', '>=', $today->copy()->startOfMonth())->sum('amount'),
                'today' => (int) (clone $payments)->whereDate('paid_on', $today)->sum('amount'),
            ];
        });
    }

    /**
     * Retards de paiement de l'année ouverte.
     *
     * @return array{amount: int, families: int}
     */
    public function overdue(): array
    {
        return $this->remember('overdue', function (): array {
            $rows = $this->year() === null ? collect() : app(UnpaidReport::class)->rows($this->year());

            return [
                'amount' => (int) $rows->sum(fn (UnpaidRow $row): int => $row->overdue),
                'families' => $rows->pluck('phone')->filter()->unique()->count() ?: $rows->count(),
            ];
        });
    }

    /**
     * Encaissements par mois depuis le début de l'année scolaire.
     *
     * @return array<string, int> « oct. » => montant
     */
    public function monthlyCollections(): array
    {
        return $this->remember('monthly', function (): array {
            $year = $this->year();

            if ($year === null) {
                return [];
            }

            $end = SchoolClock::today()->min($year->ends_on);
            $totals = Payment::query()
                ->valid()
                ->whereBetween('paid_on', [$year->starts_on->copy()->startOfMonth(), $end->copy()->endOfMonth()])
                ->selectRaw("to_char(paid_on, 'YYYY-MM') as month, sum(amount) as total")
                ->groupBy('month')
                ->toBase()
                ->pluck('total', 'month');

            $months = [];

            for ($month = $year->starts_on->copy()->startOfMonth(); $month->lte($end); $month->addMonth()) {
                $months[$month->locale('fr')->isoFormat('MMM YY')] = (int) ($totals[$month->format('Y-m')] ?? 0);
            }

            return $months;
        });
    }

    /**
     * @template T
     *
     * @param  callable(): T  $compute
     * @return T
     */
    private function remember(string $key, callable $compute): mixed
    {
        if (! array_key_exists($key, $this->memo)) {
            $this->memo[$key] = $compute();
        }

        return $this->memo[$key];
    }

    public static function since(CarbonInterface $date): string
    {
        $minutes = (int) $date->diffInMinutes(now());

        return match (true) {
            $minutes < 1 => 'à l\'instant',
            $minutes < 60 => "il y a {$minutes} min",
            $minutes < 24 * 60 => 'il y a '.intdiv($minutes, 60).' h',
            default => $date->format('d/m'),
        };
    }
}
