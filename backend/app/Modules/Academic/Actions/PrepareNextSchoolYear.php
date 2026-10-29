<?php

declare(strict_types=1);

namespace App\Modules\Academic\Actions;

use App\Modules\Academic\Events\NextSchoolYearPrepared;
use App\Modules\Academic\Models\ClassSubject;
use App\Modules\Academic\Models\SchoolClass;
use App\Modules\Academic\Models\SchoolYear;
use Illuminate\Support\Facades\DB;

/**
 * Prépare l'année N+1 à partir de l'année N (docs/PRODUCT_ARCHITECTURE.md §9) :
 * nouvelle année « en préparation » avec ses périodes, mêmes classes, mêmes
 * matières, coefficients et enseignants. L'école ajuste ensuite.
 *
 * Les élèves ne sont pas copiés : ils passent par les réinscriptions
 * (module Inscriptions).
 */
final class PrepareNextSchoolYear
{
    public function __construct(private readonly CreateSchoolYear $createSchoolYear) {}

    public function handle(SchoolYear $from): SchoolYear
    {
        return DB::transaction(function () use ($from): SchoolYear {
            $next = $this->createSchoolYear->handle($from->starts_on->year + 1);

            $from->schoolClasses()
                ->with('classSubjects')
                ->get()
                ->each(function (SchoolClass $schoolClass) use ($next): void {
                    $copy = $schoolClass->replicate(['uuid', 'school_year_id']);
                    $copy->school_year_id = $next->id;
                    $copy->save();

                    $schoolClass->classSubjects->each(function (ClassSubject $classSubject) use ($copy): void {
                        ClassSubject::create([
                            'school_class_id' => $copy->id,
                            'subject_id' => $classSubject->subject_id,
                            'coefficient' => $classSubject->coefficient,
                            'teacher_id' => $classSubject->teacher_id,
                        ]);
                    });
                });

            NextSchoolYearPrepared::dispatch($from, $next);

            return $next;
        });
    }
}
