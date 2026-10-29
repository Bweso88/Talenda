<?php

namespace App\Filament\School\Resources\SchoolYears\Pages;

use App\Filament\School\Resources\SchoolYears\SchoolYearResource;
use App\Modules\Academic\Actions\CreateSchoolYear as CreateSchoolYearAction;
use App\Modules\Core\Exceptions\BusinessRuleViolation;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateSchoolYear extends CreateRecord
{
    protected static string $resource = SchoolYearResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(CreateSchoolYearAction::class)->handle((int) $data['start_year']);
        } catch (BusinessRuleViolation $exception) {
            throw ValidationException::withMessages(['data.start_year' => $exception->getMessage()]);
        }
    }
}
