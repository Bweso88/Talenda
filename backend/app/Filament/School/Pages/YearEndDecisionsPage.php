<?php

namespace App\Filament\School\Pages;

use App\Filament\School\Support\NavigationGroups;
use App\Models\User;
use App\Modules\Academic\Models\SchoolClass;
use App\Modules\Academic\Models\SchoolYear;
use App\Modules\Assessments\Actions\YearEndDecisions;
use App\Modules\Assessments\Models\AnnualResult;
use App\Modules\Assessments\Models\GradingSetting;
use App\Modules\Core\Exceptions\BusinessRuleViolation;
use App\Modules\Enrollments\Enums\EnrollmentStatus;
use App\Modules\Enrollments\Enums\YearEndDecision;
use App\Modules\Enrollments\Models\Enrollment;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

/**
 * Décisions de fin d'année par classe, à partir des moyennes annuelles.
 * Elles déterminent le niveau proposé lors des réinscriptions.
 *
 * @property-read Schema $form
 */
class YearEndDecisionsPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroups::GRADES;

    protected static ?int $navigationSort = 40;

    protected static ?string $slug = 'decisions-de-fin-d-annee';

    protected static ?string $title = 'Décisions de fin d\'année';

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    public static function canAccess(): bool
    {
        return Filament::auth()->user()?->can('term.manage') ?? false;
    }

    public function mount(): void
    {
        $this->form->fill(['school_class_id' => SchoolClass::query()->where('school_year_id', SchoolYear::query()->open()->value('id'))->orderBy('name')->value('id')]);
        $this->loadDecisions();
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedSchema::make('form')]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make()
                    ->components([
                        Select::make('school_class_id')
                            ->label('Classe')
                            ->options(fn (): array => SchoolClass::query()
                                ->where('school_year_id', SchoolYear::query()->open()->value('id'))
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->live()
                            ->afterStateUpdated(fn () => $this->loadDecisions()),
                        Text::make('La moyenne annuelle est la moyenne des trimestres publiés. Proposition : admis au-dessus de la moyenne de passage, redoublement en dessous ; à ajuster au cas par cas.'),
                    ]),
                Section::make('Élèves')
                    ->visible(fn (): bool => $this->schoolClass() !== null)
                    ->components(fn (): array => $this->rows()),
            ]);
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('propose')
                ->label('Appliquer les propositions')
                ->icon(Heroicon::OutlinedSparkles)
                ->color('gray')
                ->visible(fn (): bool => $this->schoolClass() !== null)
                ->action(function (): void {
                    $settings = GradingSetting::current();
                    $annual = $this->annualResults();

                    foreach ($this->enrollments() as $enrollment) {
                        $this->data['decisions'][$enrollment->id] = YearEndDecisions::propose($enrollment, $annual->get($enrollment->id), $settings)->value
                            ?? ($this->data['decisions'][$enrollment->id] ?? null);
                    }
                }),
            Action::make('save')
                ->label('Enregistrer les décisions')
                ->icon(Heroicon::OutlinedCheck)
                ->visible(fn (): bool => $this->schoolClass() !== null)
                ->action(function (): void {
                    $schoolClass = $this->schoolClass();

                    if ($schoolClass === null) {
                        return;
                    }

                    try {
                        /** @var User $user */
                        $user = Filament::auth()->user();
                        app(YearEndDecisions::class)->save($schoolClass, (array) ($this->data['decisions'] ?? []), $user);
                    } catch (BusinessRuleViolation $exception) {
                        Notification::make()->danger()->title($exception->getMessage())->send();

                        return;
                    }

                    Notification::make()->success()->title('Décisions enregistrées.')->send();
                }),
        ];
    }

    /**
     * @return list<Grid>
     */
    private function rows(): array
    {
        $annual = $this->annualResults();
        $settings = GradingSetting::current();

        return $this->enrollments()->map(function (Enrollment $enrollment) use ($annual, $settings): Grid {
            $result = $annual->get($enrollment->id);
            $proposal = YearEndDecisions::propose($enrollment, $result, $settings);

            return Grid::make(12)->components([
                Text::make($enrollment->student->full_name)->weight('bold')->columnSpan(4),
                Text::make($result?->average === null ? 'Pas de moyenne' : number_format((float) $result->average, 2, ',', '').' / 20')->columnSpan(2),
                Text::make($result?->rank === null ? '—' : "{$result->rank}e / {$result->class_size}")->columnSpan(1),
                Text::make('Proposé : '.($proposal?->getLabel() ?? '—'))->color('gray')->columnSpan(2),
                Select::make("decisions.{$enrollment->id}")->hiddenLabel()->options(YearEndDecision::class)->columnSpan(3),
            ]);
        })->values()->all();
    }

    /**
     * @return Collection<int, Enrollment>
     */
    private function enrollments(): Collection
    {
        $schoolClass = $this->schoolClass();

        if ($schoolClass === null) {
            return new Collection;
        }

        return $schoolClass->enrollments()
            ->whereIn('status', [EnrollmentStatus::Enrolled, EnrollmentStatus::Completed])
            ->with(['student', 'schoolClass.level'])
            ->get()
            ->sortBy(fn (Enrollment $enrollment): string => $enrollment->student->last_name.' '.$enrollment->student->first_names)
            ->values();
    }

    /**
     * @return Collection<int, AnnualResult>
     */
    private function annualResults(): Collection
    {
        return AnnualResult::query()->whereIn('enrollment_id', $this->enrollments()->modelKeys())->get()->keyBy('enrollment_id');
    }

    private function loadDecisions(): void
    {
        $this->data['decisions'] = $this->enrollments()->mapWithKeys(fn (Enrollment $enrollment): array => [$enrollment->id => $enrollment->decision?->value])->all();
    }

    private function schoolClass(): ?SchoolClass
    {
        $id = $this->data['school_class_id'] ?? null;

        return blank($id) ? null : SchoolClass::query()->with('schoolYear')->find($id);
    }
}
