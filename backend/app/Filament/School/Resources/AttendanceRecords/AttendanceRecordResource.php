<?php

namespace App\Filament\School\Resources\AttendanceRecords;

use App\Filament\School\Resources\AttendanceRecords\Pages\ListAttendanceRecords;
use App\Filament\School\Support\NavigationGroups;
use App\Filament\Support\FrenchLabels;
use App\Models\User;
use App\Modules\Academic\Enums\SchoolYearStatus;
use App\Modules\Academic\Models\SchoolClass;
use App\Modules\Core\Exceptions\BusinessRuleViolation;
use App\Modules\SchoolLife\Actions\JustifyAbsences;
use App\Modules\SchoolLife\Enums\AttendanceStatus;
use App\Modules\SchoolLife\Enums\JustificationReason;
use App\Modules\SchoolLife\Models\AttendanceRecord;
use App\Modules\SchoolLife\Models\AttendanceSession;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use UnitEnum;

/**
 * Suivi des absences et des retards relevés aux appels, avec leur
 * justification. Lecture seule : on corrige un appel depuis l'écran
 * d'appel.
 */
class AttendanceRecordResource extends Resource
{
    use FrenchLabels;

    protected static ?string $model = AttendanceRecord::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserMinus;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroups::SCHOOL_LIFE;

    protected static ?int $navigationSort = 20;

    protected static ?string $slug = 'absences';

    protected static ?string $navigationLabel = 'Absences et retards';

    protected static ?string $modelLabel = 'absence';

    protected static ?string $pluralModelLabel = 'absences et retards';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return AttendanceRecord::selectJustified(parent::getEloquentQuery())
            ->with(['session.slot', 'session.schoolClass', 'enrollment.student'])
            ->orderByDesc(
                AttendanceSession::query()
                    ->select('held_on')
                    ->whereColumn('attendance_sessions.id', 'attendance_records.attendance_session_id'),
            );
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('session.held_on')->label('Date')->date('d/m/Y'),
                TextColumn::make('session.slot.name')->label('Créneau'),
                TextColumn::make('enrollment.student.full_name')->label('Élève')->weight('bold')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('enrollment.student', fn (Builder $student) => $student
                        ->whereRaw('lower(last_name) like ?', ['%'.mb_strtolower($search).'%'])
                        ->orWhereRaw('lower(first_names) like ?', ['%'.mb_strtolower($search).'%']))),
                TextColumn::make('session.schoolClass.name')->label('Classe'),
                TextColumn::make('status')->label('Type')->badge()
                    ->formatStateUsing(fn (AttendanceRecord $record): string => $record->status === AttendanceStatus::Late && $record->late_minutes
                        ? "Retard ({$record->late_minutes} min)"
                        : $record->status->getLabel()),
                IconColumn::make('is_justified')->label('Justifiée')->boolean()
                    ->state(fn (AttendanceRecord $record): ?bool => $record->status === AttendanceStatus::Absent ? (bool) $record->is_justified : null),
                TextColumn::make('note')->label('Remarque')->placeholder('—')->wrap(),
            ])
            ->filters([
                SelectFilter::make('school_class_id')
                    ->label('Classe')
                    ->options(fn (): array => SchoolClass::query()
                        ->whereHas('schoolYear', fn (Builder $query) => $query->where('status', SchoolYearStatus::Open))
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->query(fn (Builder $query, array $data): Builder => blank($data['value'] ?? null)
                        ? $query
                        : $query->whereHas('session', fn (Builder $session) => $session->where('school_class_id', $data['value']))),
                SelectFilter::make('status')->label('Type')->options([
                    AttendanceStatus::Absent->value => 'Absences',
                    AttendanceStatus::Late->value => 'Retards',
                ]),
                TernaryFilter::make('justified')
                    ->label('Justification')
                    ->trueLabel('Absences justifiées')
                    ->falseLabel('Absences non justifiées')
                    ->queries(
                        true: fn (Builder $query) => AttendanceRecord::whereJustified($query->where('status', AttendanceStatus::Absent)),
                        false: fn (Builder $query) => AttendanceRecord::whereJustified($query->where('status', AttendanceStatus::Absent), false),
                        blank: fn (Builder $query) => $query,
                    ),
                Filter::make('period')
                    ->schema([
                        DatePicker::make('from')->label('Du'),
                        DatePicker::make('until')->label('Au'),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data): Builder => $query->whereHas('session', fn (Builder $session) => $session
                        ->when($data['from'] ?? null, fn (Builder $q, $from) => $q->whereDate('held_on', '>=', $from))
                        ->when($data['until'] ?? null, fn (Builder $q, $until) => $q->whereDate('held_on', '<=', $until)))),
            ])
            ->recordActions([
                Action::make('justify')
                    ->label('Justifier')
                    ->icon(Heroicon::OutlinedDocumentCheck)
                    ->visible(fn (AttendanceRecord $record): bool => $record->status === AttendanceStatus::Absent && ! $record->is_justified)
                    ->fillForm(fn (AttendanceRecord $record): array => [
                        'starts_on' => $record->session->held_on->toDateString(),
                        'ends_on' => $record->session->held_on->toDateString(),
                        'reason' => JustificationReason::Illness->value,
                    ])
                    ->schema([
                        DatePicker::make('starts_on')->label('Du')->required(),
                        DatePicker::make('ends_on')->label('Au')->required()->afterOrEqual('starts_on'),
                        Select::make('reason')->label('Motif')->options(JustificationReason::class)->required(),
                        Textarea::make('comment')->label('Commentaire')->placeholder('Ex. certificat médical du Dr …')->maxLength(500),
                    ])
                    ->modalHeading(fn (AttendanceRecord $record): string => "Justifier l'absence de {$record->enrollment->student->full_name}")
                    ->modalDescription('Le justificatif couvre toutes les absences de l\'élève sur ces dates.')
                    ->action(function (AttendanceRecord $record, array $data): void {
                        /** @var User $user */
                        $user = Filament::auth()->user();

                        try {
                            $justification = app(JustifyAbsences::class)->handle(
                                $record->enrollment,
                                Carbon::parse($data['starts_on']),
                                Carbon::parse($data['ends_on']),
                                $data['reason'] instanceof JustificationReason ? $data['reason'] : JustificationReason::from($data['reason']),
                                $data['comment'] ?? null,
                                $user,
                            );
                        } catch (BusinessRuleViolation $exception) {
                            Notification::make()->danger()->title($exception->getMessage())->send();

                            return;
                        }

                        $covered = JustifyAbsences::coveredAbsences($justification);
                        Notification::make()->success()->title("Justificatif enregistré : {$covered} absence(s) justifiée(s).")->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAttendanceRecords::route('/'),
        ];
    }
}
