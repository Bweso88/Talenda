<?php

declare(strict_types=1);

namespace App\Modules\SchoolLife\Support;

/**
 * Absences et retards d'un élève sur une période.
 */
final readonly class AttendanceTotal
{
    public function __construct(
        public int $absenceMinutes = 0,
        public int $justifiedAbsenceMinutes = 0,
        public int $lateCount = 0,
    ) {}

    public function unjustifiedAbsenceMinutes(): int
    {
        return $this->absenceMinutes - $this->justifiedAbsenceMinutes;
    }

    /**
     * Résumé court : « 6 h dont 2 h non justifiées · 1 retard », « — ».
     */
    public function shortLabel(): string
    {
        $parts = [];

        if ($this->absenceMinutes > 0) {
            $parts[] = self::hours($this->absenceMinutes)
                .($this->unjustifiedAbsenceMinutes() > 0 ? ' dont '.self::hours($this->unjustifiedAbsenceMinutes()).' non justifiées' : ', justifiées');
        }

        if ($this->lateCount > 0) {
            $parts[] = $this->lateCount.' retard'.($this->lateCount > 1 ? 's' : '');
        }

        return $parts === [] ? '—' : implode(' · ', $parts);
    }

    /**
     * « 7 h 30 », « 5 h », « 0 h ».
     */
    public static function hours(int $minutes): string
    {
        $rest = $minutes % 60;

        return intdiv($minutes, 60).' h'.($rest === 0 ? '' : ' '.str_pad((string) $rest, 2, '0', STR_PAD_LEFT));
    }
}
