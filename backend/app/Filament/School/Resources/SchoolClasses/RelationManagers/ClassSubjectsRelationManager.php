<?php

namespace App\Filament\School\Resources\SchoolClasses\RelationManagers;

use App\Modules\Academic\Actions\CopyClassSubjects;
use App\Modules\Academic\Models\SchoolClass;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

/**
 * Matières de la classe : coefficient (pour la moyenne générale) et
 * enseignant.
 */
class ClassSubjectsRelationManager extends RelationManager
{
    protected static string $relationship = 'classSubjects';

    protected static ?string $title = 'Matières et coefficients';

    protected static ?string $modelLabel = 'matière';

    public function isReadOnly(): bool
    {
        return ! (Filament::auth()->user()?->can('update', $this->getOwnerRecord()) ?? false);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Select::make('subject_id')
                    ->label('Matière')
                    ->relationship('subject', 'name', fn (Builder $query) => $query->orderBy('name'))
                    ->required()
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule): Unique => $rule->where('school_class_id', $this->getOwnerRecord()->getKey()),
                    )
                    ->validationMessages(['unique' => 'Cette matière est déjà enseignée dans la classe.']),
                TextInput::make('coefficient')
                    ->label('Coefficient')
                    ->numeric()
                    ->step(0.5)
                    ->minValue(0.5)
                    ->maxValue(20)
                    ->default(1)
                    ->required(),
                Select::make('teacher_id')
                    ->label('Enseignant')
                    ->relationship('teacher', 'last_name', fn (Builder $query) => $query->where('is_active', true)->orderBy('last_name'))
                    ->getOptionLabelFromRecordUsing(fn ($record): string => $record->full_name)
                    ->searchable(['first_name', 'last_name']),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('subject.name')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['subject', 'teacher']))
            ->defaultSort('coefficient', 'desc')
            ->paginated(false)
            ->columns([
                TextColumn::make('subject.name')->label('Matière'),
                TextColumn::make('coefficient')->label('Coefficient')->numeric(decimalPlaces: 1)
                    ->summarize(Sum::make()->label('Total')->numeric(decimalPlaces: 1)),
                TextColumn::make('teacher.full_name')->label('Enseignant')->placeholder('Non affecté'),
            ])
            ->headerActions([
                CreateAction::make(),
                $this->copyFromClassAction(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    private function copyFromClassAction(): Action
    {
        return Action::make('copyFromClass')
            ->label('Copier depuis une autre classe')
            ->icon(Heroicon::OutlinedDocumentDuplicate)
            ->color('gray')
            ->schema([
                Select::make('source_class_id')
                    ->label('Classe modèle')
                    ->helperText('Les matières absentes sont ajoutées avec leurs coefficients ; les enseignants ne sont pas copiés.')
                    ->options(function (): array {
                        /** @var SchoolClass $owner */
                        $owner = $this->getOwnerRecord();

                        return SchoolClass::query()
                            ->where('school_year_id', $owner->school_year_id)
                            ->whereKeyNot($owner->getKey())
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all();
                    })
                    ->required(),
            ])
            ->action(function (array $data): void {
                /** @var SchoolClass $owner */
                $owner = $this->getOwnerRecord();
                $source = SchoolClass::query()->findOrFail($data['source_class_id']);

                $added = app(CopyClassSubjects::class)->handle($source, $owner);

                Notification::make()
                    ->success()
                    ->title($added === 0 ? 'Aucune matière à ajouter.' : "{$added} matière(s) ajoutée(s).")
                    ->send();
            });
    }
}
