<?php

namespace App\Filament\School\Resources\Assessments;

use App\Filament\School\Resources\Assessments\Pages\CreateAssessment;
use App\Filament\School\Resources\Assessments\Pages\EditAssessment;
use App\Filament\School\Resources\Assessments\Pages\EnterScores;
use App\Filament\School\Resources\Assessments\Pages\ListAssessments;
use App\Filament\School\Resources\Assessments\Schemas\AssessmentForm;
use App\Filament\School\Resources\Assessments\Tables\AssessmentsTable;
use App\Filament\School\Support\NavigationGroups;
use App\Filament\Support\FrenchLabels;
use App\Models\User;
use App\Modules\Assessments\Models\Assessment;
use App\Modules\Assessments\Support\GradableClassSubjects;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class AssessmentResource extends Resource
{
    use FrenchLabels;

    protected static ?string $model = Assessment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroups::GRADES;

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'evaluations';

    protected static ?string $modelLabel = 'évaluation';

    protected static ?string $recordTitleAttribute = 'title';

    /**
     * Un enseignant ne voit que les évaluations de ses matières.
     *
     * @return Builder<Model>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var User $user */
        $user = Filament::auth()->user();

        return parent::getEloquentQuery()
            ->whereIn('class_subject_id', GradableClassSubjects::for($user)->select('id'));
    }

    public static function form(Schema $schema): Schema
    {
        return AssessmentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AssessmentsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAssessments::route('/'),
            'create' => CreateAssessment::route('/nouvelle'),
            'edit' => EditAssessment::route('/{record}/modifier'),
            'scores' => EnterScores::route('/{record}/notes'),
        ];
    }
}
