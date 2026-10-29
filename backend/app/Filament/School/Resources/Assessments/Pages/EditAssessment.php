<?php

namespace App\Filament\School\Resources\Assessments\Pages;

use App\Filament\School\Resources\Assessments\AssessmentResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditAssessment extends EditRecord
{
    protected static string $resource = AssessmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('scores')
                ->label('Saisir les notes')
                ->icon(Heroicon::OutlinedPencil)
                ->url(fn (): string => AssessmentResource::getUrl('scores', ['record' => $this->getRecord()])),
            DeleteAction::make(),
        ];
    }
}
