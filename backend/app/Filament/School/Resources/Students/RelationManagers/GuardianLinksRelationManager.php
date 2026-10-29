<?php

namespace App\Filament\School\Resources\Students\RelationManagers;

use App\Modules\Core\Exceptions\BusinessRuleViolation;
use App\Modules\Core\Support\PhoneNumber;
use App\Modules\Enrollments\Actions\LinkGuardian;
use App\Modules\Enrollments\Enums\GuardianRelationship;
use App\Modules\Enrollments\Models\Student;
use App\Modules\Enrollments\Models\StudentGuardian;
use Closure;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Responsables de l'élève. Modifier les coordonnées d'un responsable les
 * modifie pour tous ses enfants (c'est la même personne).
 */
class GuardianLinksRelationManager extends RelationManager
{
    protected static string $relationship = 'guardianLinks';

    protected static ?string $title = 'Responsables';

    protected static ?string $modelLabel = 'responsable';

    public function isReadOnly(): bool
    {
        return ! (Filament::auth()->user()?->can('update', $this->getOwnerRecord()) ?? false);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Select::make('relationship')
                    ->label('Lien')
                    ->options(GuardianRelationship::class)
                    ->required(),
                TextInput::make('last_name')
                    ->label('Nom')
                    ->required()
                    ->maxLength(100),
                TextInput::make('first_names')
                    ->label('Prénoms')
                    ->maxLength(150),
                TextInput::make('phone')
                    ->label('Téléphone')
                    ->tel()
                    ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                        if (filled($value) && PhoneNumber::normalize((string) $value) === null) {
                            $fail('Numéro de téléphone invalide.');
                        }
                    }),
                TextInput::make('profession')
                    ->label('Profession')
                    ->maxLength(100),
                TextInput::make('email')
                    ->label('E-mail')
                    ->email(),
                Toggle::make('is_primary_contact')
                    ->label('Contact principal'),
                Toggle::make('is_financially_responsible')
                    ->label('Paie la scolarité'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('guardian'))
            ->paginated(false)
            ->columns([
                TextColumn::make('guardian.full_name')->label('Nom')->weight('bold'),
                TextColumn::make('relationship')->label('Lien'),
                TextColumn::make('guardian.phone')
                    ->label('Téléphone')
                    ->formatStateUsing(fn (?string $state): ?string => PhoneNumber::format($state))
                    ->placeholder('—'),
                TextColumn::make('guardian.profession')->label('Profession')->placeholder('—'),
                IconColumn::make('is_primary_contact')->label('Contact principal')->boolean(),
                IconColumn::make('is_financially_responsible')->label('Paie la scolarité')->boolean(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Ajouter un responsable')
                    ->using(function (array $data): Model {
                        /** @var Student $student */
                        $student = $this->getOwnerRecord();

                        try {
                            return app(LinkGuardian::class)->handle($student, $data);
                        } catch (BusinessRuleViolation $exception) {
                            Notification::make()->danger()->title($exception->getMessage())->send();

                            throw new Halt;
                        }
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->fillForm(fn (StudentGuardian $record): array => [
                        ...$record->guardian->only(['last_name', 'first_names', 'phone', 'profession', 'email']),
                        ...$record->only(['relationship', 'is_primary_contact', 'is_financially_responsible']),
                    ])
                    ->using(function (StudentGuardian $record, array $data): StudentGuardian {
                        $record->guardian->update(collect($data)->only(['last_name', 'first_names', 'phone', 'profession', 'email'])->all());

                        if ($data['is_primary_contact'] ?? false) {
                            $record->student->guardianLinks()->whereKeyNot($record->id)->update(['is_primary_contact' => false]);
                        }

                        $record->update(collect($data)->only(['relationship', 'is_primary_contact', 'is_financially_responsible'])->all());

                        return $record;
                    }),
                DeleteAction::make()->label('Retirer'),
            ]);
    }
}
