<?php

namespace App\Filament\School\Pages;

use App\Filament\School\Support\NavigationGroups;
use App\Models\User;
use App\Modules\Academic\Enums\TermStatus;
use App\Modules\Academic\Models\ClassSubject;
use App\Modules\Academic\Models\SchoolClass;
use App\Modules\Academic\Models\SchoolYear;
use App\Modules\Academic\Models\Term;
use App\Modules\Assessments\Actions\IssueReportCards;
use App\Modules\Assessments\Actions\ManageTerm;
use App\Modules\Assessments\Actions\SaveTermComments;
use App\Modules\Assessments\Models\Assessment;
use App\Modules\Assessments\Models\GradingSetting;
use App\Modules\Assessments\Models\TermComment;
use App\Modules\Assessments\Models\TermResult;
use App\Modules\Assessments\Support\ClassTermResults;
use App\Modules\Assessments\Support\ComputeClassTermResults;
use App\Modules\Core\Exceptions\BusinessRuleViolation;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * Conseil de classe : avancement de la saisie, résultats provisoires (ou
 * figés une fois publiés), appréciations du conseil, cycle de vie de la
 * période (verrouiller, publier, rouvrir) et bulletins PDF.
 *
 * @property-read Schema $form
 */
class ClassCouncil extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroups::GRADES;

    protected static ?int $navigationSort = 30;

    protected static ?string $slug = 'conseil-de-classe';

    protected static ?string $title = 'Conseil de classe et bulletins';

    protected static ?string $navigationLabel = 'Conseil de classe et bulletins';

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return ($user?->can('term.manage') ?? false) || ($user?->can('report_card.view') ?? false);
    }

    public function mount(): void
    {
        $yearId = SchoolYear::query()->open()->value('id');

        $this->form->fill([
            'term_id' => Term::query()->where('school_year_id', $yearId)->where('status', '!=', TermStatus::Published)->orderBy('position')->value('id')
                ?? Term::query()->where('school_year_id', $yearId)->orderByDesc('position')->value('id'),
            'school_class_id' => SchoolClass::query()->where('school_year_id', $yearId)->orderBy('name')->value('id'),
        ]);
        $this->loadComments();
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
                    ->columns(3)
                    ->components([
                        Select::make('term_id')
                            ->label('Période')
                            ->options(fn (): array => Term::query()
                                ->where('school_year_id', SchoolYear::query()->open()->value('id'))
                                ->orderBy('position')
                                ->get()
                                ->mapWithKeys(fn (Term $term): array => [$term->id => "{$term->name} ({$term->status->getLabel()})"])
                                ->all())
                            ->live()
                            ->afterStateUpdated(fn () => $this->loadComments()),
                        Select::make('school_class_id')
                            ->label('Classe')
                            ->options(fn (): array => SchoolClass::query()
                                ->where('school_year_id', SchoolYear::query()->open()->value('id'))
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->live()
                            ->afterStateUpdated(fn () => $this->loadComments()),
                        Text::make(fn (): string => $this->term() === null ? '' : match ($this->term()->status) {
                            TermStatus::Open => 'Saisie des notes ouverte. Verrouillez-la avant le conseil de classe.',
                            TermStatus::Locked => 'Saisie verrouillée : résultats provisoires. Publiez pour figer les bulletins.',
                            TermStatus::Published => 'Bulletins publiés : résultats figés.',
                        }),
                    ]),
                Section::make('Avancement de la saisie')
                    ->collapsible()
                    ->visible(fn (): bool => $this->ready())
                    ->components(fn (): array => $this->progress()),
                Section::make('Résultats')
                    ->visible(fn (): bool => $this->ready())
                    ->components(fn (): array => $this->resultRows()),
            ]);
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('saveComments')
                ->label('Enregistrer les appréciations')
                ->icon(Heroicon::OutlinedCheck)
                ->visible(fn (): bool => $this->ready() && $this->term()?->status !== TermStatus::Published && $this->userCan('term.manage'))
                ->action(fn () => $this->guard(fn () => app(SaveTermComments::class)->handle($this->term(), null, (array) ($this->data['comments'] ?? []), $this->user()), 'Appréciations enregistrées.')),
            Action::make('lock')
                ->label('Verrouiller la saisie')
                ->icon(Heroicon::OutlinedLockClosed)
                ->color('warning')
                ->requiresConfirmation()
                ->modalDescription('Les enseignants ne pourront plus modifier les notes de cette période, dans toutes les classes.')
                ->visible(fn (): bool => $this->term()?->status === TermStatus::Open && $this->userCan('term.manage'))
                ->action(fn () => $this->guard(fn () => app(ManageTerm::class)->lock($this->term(), $this->user()), 'Saisie verrouillée.')),
            Action::make('unlock')
                ->label('Rouvrir la saisie')
                ->icon(Heroicon::OutlinedLockOpen)
                ->color('gray')
                ->visible(fn (): bool => $this->term()?->status === TermStatus::Locked && $this->userCan('term.manage'))
                ->action(fn () => $this->guard(fn () => app(ManageTerm::class)->unlock($this->term(), $this->user()), 'Saisie rouverte.')),
            Action::make('publish')
                ->label('Publier les bulletins')
                ->icon(Heroicon::OutlinedCheckBadge)
                ->color('success')
                ->requiresConfirmation()
                ->modalDescription('Les moyennes, rangs et appréciations de toutes les classes sont figés et les bulletins deviennent disponibles.')
                ->visible(fn (): bool => $this->term()?->status === TermStatus::Locked && $this->userCan('term.manage'))
                ->action(fn () => $this->guard(fn () => app(ManageTerm::class)->publish($this->term(), $this->user()), 'Bulletins publiés.')),
            Action::make('reportCards')
                ->label('Télécharger les bulletins de la classe')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->visible(fn (): bool => $this->ready() && $this->term()?->status === TermStatus::Published)
                ->action(fn (): ?StreamedResponse => $this->downloadReportCards()),
            Action::make('reopen')
                ->label('Rouvrir les bulletins')
                ->icon(Heroicon::OutlinedArrowUturnLeft)
                ->color('danger')
                ->visible(fn (): bool => $this->term()?->status === TermStatus::Published && $this->userCan('term.reopen'))
                ->modalDescription('Les bulletins publiés de toutes les classes sont retirés ; ils seront recalculés à la prochaine publication. Cette action est journalisée.')
                ->schema([TextInput::make('reason')->label('Motif')->required()->maxLength(255)])
                ->action(fn (array $data) => $this->guard(fn () => app(ManageTerm::class)->reopen($this->term(), $this->user(), $data['reason']), 'Bulletins retirés : la période est verrouillée.')),
        ];
    }

    /**
     * @return list<Text>
     */
    private function progress(): array
    {
        $term = $this->term();
        $schoolClass = $this->schoolClass();

        if ($term === null || $schoolClass === null) {
            return [];
        }

        $students = ComputeClassTermResults::enrollmentsOf($term, $schoolClass)->count();
        $assessments = Assessment::query()->where('term_id', $term->id)->withCount('scores')->get()->groupBy('class_subject_id');

        return $schoolClass->classSubjects()->with(['subject', 'teacher'])->get()
            ->sortBy('subject.name')
            ->map(function (ClassSubject $classSubject) use ($assessments, $students): Text {
                $subjectAssessments = $assessments->get($classSubject->id, collect());
                $expected = $subjectAssessments->count() * $students;
                $entered = (int) $subjectAssessments->sum('scores_count');
                $percent = $expected === 0 ? 0 : (int) round($entered / $expected * 100);

                $teacher = $classSubject->teacher->full_name ?? 'pas d\'enseignant affecté';

                return Text::make("{$classSubject->subject->name} ({$teacher}) : {$subjectAssessments->count()} évaluation(s), {$percent} % des notes saisies")
                    ->color($subjectAssessments->isEmpty() ? 'danger' : ($percent < 100 ? 'warning' : 'success'));
            })
            ->values()
            ->all();
    }

    /**
     * @return list<Grid>
     */
    private function resultRows(): array
    {
        $term = $this->term();
        $schoolClass = $this->schoolClass();

        if ($term === null || $schoolClass === null) {
            return [];
        }

        $editable = $term->status !== TermStatus::Published && $this->userCan('term.manage');
        $rows = $term->status === TermStatus::Published ? $this->publishedRows($term, $schoolClass) : $this->provisionalRows(app(ComputeClassTermResults::class)->handle($term, $schoolClass));

        $grids = [Grid::make(12)->components([
            Text::make('Rang')->weight('bold')->columnSpan(1),
            Text::make('Élève')->weight('bold')->columnSpan(4),
            Text::make('Moyenne')->weight('bold')->columnSpan(1),
            Text::make('Mention')->weight('bold')->columnSpan(2),
            Text::make('Appréciation du conseil')->weight('bold')->columnSpan(4),
        ])];

        foreach ($rows as $row) {
            $grids[] = Grid::make(12)->components([
                Text::make($row['rank'])->columnSpan(1),
                Text::make($row['name'])->weight('bold')->columnSpan(4),
                Text::make($row['average'])->columnSpan(1),
                Text::make($row['distinction'])->color('success')->columnSpan(2),
                $editable
                    ? TextInput::make("comments.{$row['enrollment_id']}")->hiddenLabel()->maxLength(500)->columnSpan(4)
                    : Text::make($row['comment'])->columnSpan(4),
            ]);
        }

        return $grids;
    }

    /**
     * @return list<array{enrollment_id: int, rank: string, name: string, average: string, distinction: string, comment: string}>
     */
    private function provisionalRows(ClassTermResults $results): array
    {
        $settings = GradingSetting::current();
        $rows = [];

        foreach ($results->enrollments as $enrollmentId => $enrollment) {
            $rank = $results->rank($enrollmentId);
            $average = $results->average($enrollmentId);

            $rows[] = [
                'enrollment_id' => $enrollmentId,
                'rank' => $rank === null ? '—' : $rank['rank'].($rank['tied'] ? ' ex æq.' : ''),
                'name' => $enrollment->student->full_name,
                'average' => $average === null ? '—' : number_format($average, 2, ',', ''),
                'distinction' => (string) $settings->distinctionFor($average),
                'comment' => '',
            ];
        }

        usort($rows, fn (array $a, array $b): int => [(int) $a['rank'] ?: PHP_INT_MAX, $a['name']] <=> [(int) $b['rank'] ?: PHP_INT_MAX, $b['name']]);

        return $rows;
    }

    /**
     * @return list<array{enrollment_id: int, rank: string, name: string, average: string, distinction: string, comment: string}>
     */
    private function publishedRows(Term $term, SchoolClass $schoolClass): array
    {
        return TermResult::query()
            ->where('term_id', $term->id)
            ->whereHas('enrollment', fn ($query) => $query->where('school_class_id', $schoolClass->id))
            ->with('enrollment.student')
            ->get()
            ->sortBy(fn (TermResult $result): array => [$result->rank ?? PHP_INT_MAX, $result->enrollment->student->last_name])
            ->map(fn (TermResult $result): array => [
                'enrollment_id' => $result->enrollment_id,
                'rank' => $result->rank === null ? '—' : $result->rank.($result->rank_tied ? ' ex æq.' : ''),
                'name' => $result->enrollment->student->full_name,
                'average' => $result->average === null ? '—' : number_format((float) $result->average, 2, ',', ''),
                'distinction' => (string) $result->distinction,
                'comment' => (string) $result->council_comment,
            ])
            ->values()
            ->all();
    }

    private function downloadReportCards(): ?StreamedResponse
    {
        try {
            $pdf = app(IssueReportCards::class)->handle($this->term(), $this->schoolClass());
        } catch (BusinessRuleViolation $exception) {
            Notification::make()->danger()->title($exception->getMessage())->send();

            return null;
        }

        $filename = 'bulletins-'.str($this->schoolClass()?->name)->slug().'-'.str($this->term()?->name)->slug().'.pdf';

        return response()->streamDownload(fn () => print ($pdf), $filename, ['Content-Type' => 'application/pdf']);
    }

    private function loadComments(): void
    {
        $term = $this->term();
        $schoolClass = $this->schoolClass();

        $this->data['comments'] = $term === null || $schoolClass === null
            ? []
            : TermComment::query()
                ->where('term_id', $term->id)
                ->whereNull('class_subject_id')
                ->whereIn('enrollment_id', $schoolClass->enrollments()->select('id'))
                ->pluck('body', 'enrollment_id')
                ->all();
    }

    private function guard(Closure $callback, string $successMessage): void
    {
        try {
            $callback();
        } catch (BusinessRuleViolation $exception) {
            Notification::make()->danger()->title($exception->getMessage())->send();

            return;
        }

        Notification::make()->success()->title($successMessage)->send();
    }

    private function ready(): bool
    {
        return $this->term() !== null && $this->schoolClass() !== null;
    }

    private function term(): ?Term
    {
        $id = $this->data['term_id'] ?? null;

        return blank($id) ? null : Term::query()->with('schoolYear')->find($id);
    }

    private function schoolClass(): ?SchoolClass
    {
        $id = $this->data['school_class_id'] ?? null;

        return blank($id) ? null : SchoolClass::query()->find($id);
    }

    private function userCan(string $permission): bool
    {
        return Filament::auth()->user()?->can($permission) ?? false;
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Filament::auth()->user();

        return $user;
    }
}
