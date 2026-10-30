<?php

declare(strict_types=1);

namespace App\Modules\Communication\Support;

use App\Modules\Academic\Models\Cycle;
use App\Modules\Academic\Models\Level;
use App\Modules\Academic\Models\SchoolClass;
use App\Modules\Academic\Models\SchoolYear;
use App\Modules\Communication\Enums\AnnouncementAudience;
use App\Modules\Communication\Sms\SmsText;
use App\Modules\Enrollments\Enums\EnrollmentStatus;
use App\Modules\Enrollments\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Élèves inscrits cette année dans le public d'une annonce.
 */
final class AnnouncementAudienceQuery
{
    /**
     * @return Collection<int, Student>
     */
    public static function students(AnnouncementAudience $audience, ?int $targetId): Collection
    {
        $yearId = SchoolYear::query()->open()->value('id') ?? 0;

        return Student::query()
            ->whereHas('enrollments', fn (Builder $enrollment) => $enrollment
                ->where('school_year_id', $yearId)
                ->where('status', EnrollmentStatus::Enrolled)
                ->when($audience === AnnouncementAudience::SchoolClass, fn (Builder $query) => $query->where('school_class_id', $targetId))
                ->when($audience === AnnouncementAudience::Level, fn (Builder $query) => $query->whereHas('schoolClass', fn (Builder $class) => $class->where('level_id', $targetId)))
                ->when($audience === AnnouncementAudience::Cycle, fn (Builder $query) => $query->whereHas('schoolClass.level', fn (Builder $level) => $level->where('cycle_id', $targetId))))
            ->with('guardians')
            ->get();
    }

    public static function label(AnnouncementAudience $audience, ?int $targetId): string
    {
        return match ($audience) {
            AnnouncementAudience::School => 'Toute l\'école',
            AnnouncementAudience::Cycle => 'Cycle '.Cycle::query()->findOrFail($targetId)->name,
            AnnouncementAudience::Level => 'Niveau '.Level::query()->findOrFail($targetId)->name,
            AnnouncementAudience::SchoolClass => 'Classe '.SchoolClass::query()->findOrFail($targetId)->name,
        };
    }

    /**
     * Aperçu avant publication.
     *
     * @return array{families: int, app_users: int, sms: int}
     */
    public static function estimate(AnnouncementAudience $audience, ?int $targetId, string $smsText): array
    {
        $students = self::students($audience, $targetId);
        $phones = FamilyContacts::smsPhones($students);

        return [
            'families' => count($phones),
            'app_users' => count(FamilyContacts::appUserIds($students)),
            'sms' => count($phones) * SmsText::segments(SmsText::clean($smsText)),
        ];
    }
}
