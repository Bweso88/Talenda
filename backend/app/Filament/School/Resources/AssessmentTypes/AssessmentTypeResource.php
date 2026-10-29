<?php

namespace App\Filament\School\Resources\AssessmentTypes;

use App\Filament\School\Resources\AssessmentTypes\Pages\ManageAssessmentTypes;
use App\Filament\School\Support\NavigationGroups;
use App\Filament\School\Support\TenantUnique;
use App\Modules\Assessments\Models\AssessmentType;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class AssessmentTypeResource extends Resource
{
    protected static ?string $model = AssessmentType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroups::SETTINGS;

    protected static ?int $navigationSort = 50;

    protected static ?string $slug = 'types-d-evaluation';

    protected static ?string $modelLabel = 'type d\'évaluation';

    protected static ?string $pluralModelLabel = 'types d\'évaluation';

    protected static ?string $navigationLabel = 'Types d\'évaluation';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                TextInput::make('name')->label('Nom')->required()->maxLength(100),
                TextInput::make('code')->label('Code')->required()->maxLength(30)
                    ->unique(ignoreRecord: true, modifyRuleUsing: TenantUnique::scope()),
                TextInput::make('weight')->label('Poids dans la moyenne')->numeric()->minValue(0.01)->default(1)->required()
                    ->helperText('Ex. composition 2, devoirs 1 : la composition compte double.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('position')
            ->reorderable('position')
            ->columns([
                TextColumn::make('name')->label('Type')->weight('bold'),
                TextColumn::make('code')->label('Code'),
                TextColumn::make('weight')->label('Poids'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAssessmentTypes::route('/'),
        ];
    }
}
