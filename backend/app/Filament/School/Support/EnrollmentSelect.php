<?php

namespace App\Filament\School\Support;

use App\Modules\Academic\Enums\SchoolYearStatus;
use App\Modules\Enrollments\Models\Enrollment;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Builder;

/**
 * Choix d'un élève de l'année ouverte, par nom, prénom ou matricule.
 */
final class EnrollmentSelect
{
    public static function make(string $name = 'enrollment_id'): Select
    {
        return Select::make($name)
            ->label('Élève')
            ->searchable()
            ->getSearchResultsUsing(fn (string $search): array => self::search($search))
            ->getOptionLabelUsing(fn (mixed $value): ?string => self::label(Enrollment::query()->with(['student', 'schoolClass'])->find($value)));
    }

    /**
     * @return array<int, string>
     */
    public static function search(string $search): array
    {
        $term = '%'.mb_strtolower(trim($search)).'%';

        return Enrollment::query()
            ->with(['student', 'schoolClass'])
            ->whereHas('schoolYear', fn (Builder $query) => $query->where('status', SchoolYearStatus::Open))
            ->whereHas('student', fn (Builder $query) => $query
                ->whereRaw('lower(last_name) like ?', [$term])
                ->orWhereRaw('lower(first_names) like ?', [$term])
                ->orWhereRaw('lower(registration_number) like ?', [$term]))
            ->limit(20)
            ->get()
            ->mapWithKeys(fn (Enrollment $enrollment): array => [$enrollment->id => (string) self::label($enrollment)])
            ->all();
    }

    public static function label(?Enrollment $enrollment): ?string
    {
        return $enrollment === null
            ? null
            : "{$enrollment->student->full_name} ({$enrollment->student->registration_number}) — {$enrollment->schoolClass->name}";
    }
}
