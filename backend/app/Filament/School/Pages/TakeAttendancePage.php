<?php

namespace App\Filament\School\Pages;

use App\Filament\School\Support\NavigationGroups;
use App\Models\User;
use App\Modules\Academic\Models\SchoolClass;
use App\Modules\Core\Exceptions\BusinessRuleViolation;
use App\Modules\Core\Support\SchoolClock;
use App\Modules\Enrollments\Models\Enrollment;
use App\Modules\SchoolLife\Actions\TakeAttendance;
use App\Modules\SchoolLife\Enums\AttendanceStatus;
use App\Modules\SchoolLife\Models\AttendanceRecord;
use App\Modules\SchoolLife\Models\AttendanceSession;
use App\Modules\SchoolLife\Models\AttendanceSlot;
use App\Modules\SchoolLife\Support\AttendanceClasses;
use App\Modules\SchoolLife\Support\ClassRoll;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use UnitEnum;

/**
 * Appel d'une classe : tout le monde est présent par défaut, on ne touche
 * qu'aux absents et aux retardataires. Pensé pour un téléphone en classe.
 *
 * @property-read Schema $form
 */
class TakeAttendancePage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroups::SCHOOL_LIFE;

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'appel';

    protected static ?string $title = 'Faire l\'appel';

    protected static ?string $navigationLabel = 'Appel';

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    public static function canAccess(): bool
    {
        return Filament::auth()->user()?->can('attendance.take') ?? false;
    }

    public function mount(): void
    {
        $classes = AttendanceClasses::options($this->user());

        $this->form->fill([
            'school_class_id' => count($classes) === 1 ? array_key_first($classes) : null,
            'held_on' => SchoolClock::today()->toDateString(),
            'attendance_slot_id' => $this->currentSlotId(),
        ]);

        $this->loadRecords();
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedSchema::make('form')]);
    }

    public function form(Schema $schema): Schema
    {
        $canPickDate = $this->user()->can('attendance.manage');

        return $schema
            ->statePath('data')
            ->components([
                Section::make()
                    ->columns(3)
                    ->components([
                        Select::make('school_class_id')
                            ->label('Classe')
                            ->options(fn (): array => AttendanceClasses::options($this->user()))
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(fn () => $this->loadRecords()),
                        DatePicker::make('held_on')
                            ->label('Date')
                            ->maxDate(SchoolClock::today())
                            ->disabled(! $canPickDate)
                            ->helperText($canPickDate ? null : 'L\'appel se fait le jour même.')
                            ->live()
                            ->afterStateUpdated(fn () => $this->loadRecords()),
                        Select::make('attendance_slot_id')
                            ->label('Créneau')
                            ->options(fn (): array => AttendanceSlot::query()->orderBy('position')->get()->mapWithKeys(fn (AttendanceSlot $slot): array => [$slot->id => $slot->label()])->all())
                            ->selectablePlaceholder(false)
                            ->live()
                            ->afterStateUpdated(fn () => $this->loadRecords()),
                    ]),
                Section::make(fn (): string => $this->sessionHeading())
                    ->description('Tous les élèves sont présents par défaut : indiquez seulement les absents et les retards.')
                    ->visible(fn (): bool => $this->schoolClass() !== null && $this->slot() !== null)
                    ->components(fn (): array => $this->rows()),
                Section::make('Appels du jour')
                    ->description('Classes dont l\'appel n\'a pas encore été fait aujourd\'hui.')
                    ->visible(fn (): bool => $this->user()->can('attendance.manage'))
                    ->collapsible()
                    ->components(fn (): array => $this->missingToday()),
            ]);
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Enregistrer l\'appel')
                ->icon(Heroicon::OutlinedCheck)
                ->visible(fn (): bool => $this->schoolClass() !== null && $this->slot() !== null)
                ->action(fn () => $this->save()),
        ];
    }

    public function save(): void
    {
        $schoolClass = $this->schoolClass();
        $slot = $this->slot();

        if ($schoolClass === null || $slot === null) {
            return;
        }

        try {
            $session = app(TakeAttendance::class)->handle($schoolClass, $this->date(), $slot, (array) ($this->data['records'] ?? []), $this->user());
        } catch (BusinessRuleViolation $exception) {
            Notification::make()->danger()->title($exception->getMessage())->send();

            return;
        }

        $absent = $session->records()->where('status', AttendanceStatus::Absent)->count();
        $late = $session->records()->where('status', AttendanceStatus::Late)->count();

        Notification::make()->success()
            ->title("Appel enregistré : {$absent} absent(s), {$late} retard(s).")
            ->send();
    }

    /**
     * @return list<Grid>
     */
    private function rows(): array
    {
        $schoolClass = $this->schoolClass();

        if ($schoolClass === null) {
            return [];
        }

        return ClassRoll::on($schoolClass, $this->date())->map(fn (Enrollment $enrollment, int $index): Grid => Grid::make(12)->components([
            Text::make(($index + 1).'. '.$enrollment->student->full_name)
                ->weight('bold')
                ->columnSpan(['default' => 12, 'md' => 4]),
            ToggleButtons::make("records.{$enrollment->id}.status")
                ->hiddenLabel()
                ->options(AttendanceStatus::class)
                ->grouped()
                ->live()
                ->columnSpan(['default' => 12, 'md' => 3]),
            TextInput::make("records.{$enrollment->id}.late_minutes")
                ->hiddenLabel()
                ->placeholder('Retard')
                ->suffix('min')
                ->visible(fn (Get $get): bool => self::isLate($get("records.{$enrollment->id}.status")))
                ->numeric()
                ->minValue(1)
                ->maxValue(600)
                ->columnSpan(['default' => 12, 'md' => 2]),
            TextInput::make("records.{$enrollment->id}.note")
                ->hiddenLabel()
                ->placeholder('Remarque')
                ->maxLength(255)
                ->columnSpan(['default' => 12, 'md' => 3]),
        ]))->all();
    }

    /**
     * Remplit la grille avec l'appel déjà fait, s'il existe.
     */
    private function loadRecords(): void
    {
        $schoolClass = $this->schoolClass();
        $session = $this->session();

        if ($schoolClass === null) {
            $this->data['records'] = [];

            return;
        }

        $records = $session?->records()->get()->keyBy('enrollment_id') ?? collect();

        $this->data['records'] = ClassRoll::on($schoolClass, $this->date())->mapWithKeys(function (Enrollment $enrollment) use ($records): array {
            /** @var AttendanceRecord|null $record */
            $record = $records->get($enrollment->id);

            return [$enrollment->id => [
                'status' => ($record->status ?? AttendanceStatus::Present)->value,
                'late_minutes' => $record?->late_minutes,
                'note' => $record?->note,
            ]];
        })->all();
    }

    private function sessionHeading(): string
    {
        $session = $this->session();

        if ($session === null) {
            return 'Appel à faire';
        }

        $session->loadMissing('taker');

        return 'Appel déjà fait'
            .($session->taker ? ' par '.$session->taker->name : '')
            .' à '.$session->updated_at?->timezone(Filament::getTenant()?->getAttribute('timezone') ?? config('app.timezone'))->format('H:i')
            .' — vous pouvez le corriger';
    }

    /**
     * @return list<Text>
     */
    private function missingToday(): array
    {
        $today = SchoolClock::today();
        $classes = AttendanceClasses::for($this->user())->orderBy('name')->pluck('name', 'id');
        $done = AttendanceSession::query()
            ->whereDate('held_on', $today)
            ->get(['school_class_id', 'attendance_slot_id'])
            ->groupBy('attendance_slot_id')
            ->map(fn ($sessions) => $sessions->pluck('school_class_id')->all());

        return AttendanceSlot::query()->orderBy('position')->get()->map(function (AttendanceSlot $slot) use ($classes, $done): Text {
            $missing = $classes->except($done->get($slot->id, []));

            return Text::make("{$slot->name} : ".($missing->isEmpty() ? 'tous les appels sont faits.' : $missing->implode(', ')));
        })->all();
    }

    private static function isLate(mixed $status): bool
    {
        return ($status instanceof AttendanceStatus ? $status : AttendanceStatus::tryFrom((string) $status)) === AttendanceStatus::Late;
    }

    private function currentSlotId(): ?int
    {
        $now = now(Filament::getTenant()?->getAttribute('timezone') ?? config('app.timezone'))->format('H:i:s');
        $slots = AttendanceSlot::query()->orderBy('position')->get();

        return ($slots->first(fn (AttendanceSlot $slot): bool => $slot->ends_at >= $now) ?? $slots->last())?->id;
    }

    private function session(): ?AttendanceSession
    {
        $schoolClass = $this->schoolClass();
        $slot = $this->slot();

        if ($schoolClass === null || $slot === null) {
            return null;
        }

        return AttendanceSession::query()
            ->where('school_class_id', $schoolClass->id)
            ->where('attendance_slot_id', $slot->id)
            ->whereDate('held_on', $this->date())
            ->first();
    }

    private function schoolClass(): ?SchoolClass
    {
        $id = $this->data['school_class_id'] ?? null;

        return blank($id) ? null : AttendanceClasses::for($this->user())->find($id);
    }

    private function slot(): ?AttendanceSlot
    {
        $id = $this->data['attendance_slot_id'] ?? null;

        return blank($id) ? null : AttendanceSlot::query()->find($id);
    }

    private function date(): Carbon
    {
        $date = $this->data['held_on'] ?? null;

        return blank($date) ? SchoolClock::today() : Carbon::parse($date)->startOfDay();
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Filament::auth()->user();

        return $user;
    }
}
