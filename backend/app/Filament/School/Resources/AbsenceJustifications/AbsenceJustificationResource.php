<?php

namespace App\Filament\School\Resources\AbsenceJustifications;

use App\Filament\School\Resources\AbsenceJustifications\Pages\ManageAbsenceJustifications;
use App\Filament\School\Support\EnrollmentSelect;
use App\Filament\School\Support\NavigationGroups;
use App\Filament\Support\FrenchLabels;
use App\Models\User;
use App\Modules\Core\Exceptions\BusinessRuleViolation;
use App\Modules\SchoolLife\Actions\JustifyAbsences;
use App\Modules\SchoolLife\Enums\JustificationReason;
use App\Modules\SchoolLife\Enums\JustificationStatus;
use App\Modules\SchoolLife\Models\AbsenceJustification;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Justificatifs d'absence (certificats médicaux, mots des parents). Un
 * justificatif ne se modifie pas : on le retire et on en saisit un autre.
 */
class AbsenceJustificationResource extends Resource
{
    use FrenchLabels;

    protected static ?string $model = AbsenceJustification::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroups::SCHOOL_LIFE;

    protected static ?int $navigationSort = 30;

    protected static ?string $slug = 'justificatifs';

    protected static ?string $navigationLabel = 'Justificatifs';

    protected static ?string $modelLabel = 'justificatif';

    protected static ?string $pluralModelLabel = 'justificatifs';

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                EnrollmentSelect::make()->required()->columnSpanFull(),
                DatePicker::make('starts_on')->label('Absent du')->required()->default(today()),
                DatePicker::make('ends_on')->label('Au (inclus)')->required()->default(today())->afterOrEqual('starts_on'),
                Select::make('reason')->label('Motif')->options(JustificationReason::class)->required()->default(JustificationReason::Illness->value),
                Textarea::make('comment')->label('Commentaire')->placeholder('Ex. certificat médical du Dr …, mot du père')->maxLength(500)->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['enrollment.student', 'enrollment.schoolClass', 'recorder']))
            ->defaultSort('starts_on', 'desc')
            ->columns([
                TextColumn::make('enrollment.student.full_name')->label('Élève')->weight('bold'),
                TextColumn::make('enrollment.schoolClass.name')->label('Classe'),
                TextColumn::make('starts_on')->label('Du')->date('d/m/Y')->sortable(),
                TextColumn::make('ends_on')->label('Au')->date('d/m/Y'),
                TextColumn::make('reason')->label('Motif'),
                TextColumn::make('comment')->label('Commentaire')->placeholder('—')->limit(40)->tooltip(fn (AbsenceJustification $record): ?string => $record->comment),
                TextColumn::make('status')->label('Statut')->badge(),
                TextColumn::make('recorder.name')->label('Saisi par')->placeholder('—')
                    ->description(fn (AbsenceJustification $record): ?string => $record->recorder?->hasRole('parent') ? 'via l\'app parents' : null),
            ])
            ->filters([
                SelectFilter::make('status')->label('Statut')->options(JustificationStatus::class),
                SelectFilter::make('reason')->label('Motif')->options(JustificationReason::class),
            ])
            ->recordActions([
                Action::make('accept')
                    ->label('Accepter')
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('success')
                    ->visible(fn (AbsenceJustification $record): bool => $record->status === JustificationStatus::Pending)
                    ->action(fn (AbsenceJustification $record) => self::review($record, true)),
                Action::make('reject')
                    ->label('Refuser')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (AbsenceJustification $record): bool => $record->status === JustificationStatus::Pending)
                    ->action(fn (AbsenceJustification $record) => self::review($record, false)),
                DeleteAction::make()
                    ->label('Retirer')
                    ->modalDescription('Les absences couvertes redeviendront non justifiées (sauf sur les bulletins déjà publiés).')
                    ->using(function (AbsenceJustification $record): bool {
                        /** @var User $user */
                        $user = Filament::auth()->user();
                        app(JustifyAbsences::class)->withdraw($record, $user);

                        return true;
                    }),
            ]);
    }

    /**
     * Justificatifs déposés par les parents, en attente d'examen.
     */
    public static function getNavigationBadge(): ?string
    {
        $pending = AbsenceJustification::query()->where('status', JustificationStatus::Pending)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    private static function review(AbsenceJustification $record, bool $accept): void
    {
        /** @var User $user */
        $user = Filament::auth()->user();

        try {
            app(JustifyAbsences::class)->review($record, $accept, $user);
        } catch (BusinessRuleViolation $exception) {
            Notification::make()->danger()->title($exception->getMessage())->send();

            return;
        }

        Notification::make()->success()->title($accept ? 'Justificatif accepté.' : 'Justificatif refusé.')->send();
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAbsenceJustifications::route('/'),
        ];
    }
}
