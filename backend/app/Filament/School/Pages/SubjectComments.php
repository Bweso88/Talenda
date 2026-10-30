<?php

namespace App\Filament\School\Pages;

use App\Filament\School\Support\NavigationGroups;
use App\Models\User;
use App\Modules\Academic\Enums\TermStatus;
use App\Modules\Academic\Models\ClassSubject;
use App\Modules\Academic\Models\SchoolYear;
use App\Modules\Academic\Models\Term;
use App\Modules\Assessments\Actions\SaveTermComments;
use App\Modules\Assessments\Models\TermComment;
use App\Modules\Assessments\Policies\AssessmentPolicy;
use App\Modules\Assessments\Support\ComputeClassTermResults;
use App\Modules\Assessments\Support\GradableClassSubjects;
use App\Modules\Core\Exceptions\BusinessRuleViolation;
use BackedEnum;
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
use UnitEnum;

/**
 * Appréciations de l'enseignant pour sa matière, élève par élève, avec la
 * moyenne de la période sous les yeux.
 *
 * @property-read Schema $form
 */
class SubjectComments extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftEllipsis;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroups::GRADES;

    protected static ?int $navigationSort = 20;

    protected static ?string $slug = 'appreciations';

    protected static ?string $title = 'Appréciations par matière';

    protected static ?string $navigationLabel = 'Appréciations';

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    public static function canAccess(): bool
    {
        return Filament::auth()->user()?->can('assessment.enter') ?? false;
    }

    public function mount(): void
    {
        $this->form->fill([
            'term_id' => Term::query()
                ->where('school_year_id', SchoolYear::query()->open()->value('id'))
                ->where('status', '!=', TermStatus::Published)
                ->orderBy('position')
                ->value('id'),
        ]);
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
                    ->columns(2)
                    ->components([
                        Select::make('class_subject_id')
                            ->label('Classe et matière')
                            ->options(fn (): array => GradableClassSubjects::options($this->user()))
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(fn () => $this->loadComments()),
                        Select::make('term_id')
                            ->label('Période')
                            ->options(fn (): array => Term::query()
                                ->where('school_year_id', SchoolYear::query()->open()->value('id'))
                                ->orderBy('position')
                                ->pluck('name', 'id')
                                ->all())
                            ->live()
                            ->afterStateUpdated(fn () => $this->loadComments()),
                    ]),
                Section::make('Élèves')
                    ->visible(fn (): bool => $this->classSubject() !== null && $this->term() !== null)
                    ->components(fn (): array => $this->rows()),
            ]);
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Enregistrer les appréciations')
                ->icon(Heroicon::OutlinedCheck)
                ->visible(fn (): bool => $this->classSubject() !== null && $this->term() !== null && $this->term()->status !== TermStatus::Published)
                ->action(fn () => $this->save()),
        ];
    }

    public function save(): void
    {
        $classSubject = $this->classSubject();
        $term = $this->term();

        if ($classSubject === null || $term === null) {
            return;
        }

        abort_unless(app(AssessmentPolicy::class)->canGrade($this->user(), $classSubject), 403);

        try {
            app(SaveTermComments::class)->handle($term, $classSubject, (array) ($this->data['comments'] ?? []), $this->user());
        } catch (BusinessRuleViolation $exception) {
            Notification::make()->danger()->title($exception->getMessage())->send();

            return;
        }

        Notification::make()->success()->title('Appréciations enregistrées.')->send();
    }

    /**
     * @return list<Grid>
     */
    private function rows(): array
    {
        $classSubject = $this->classSubject();
        $term = $this->term();

        if ($classSubject === null || $term === null) {
            return [];
        }

        $results = app(ComputeClassTermResults::class)->handle($term, $classSubject->schoolClass);
        $readOnly = $term->status === TermStatus::Published;

        return array_values(array_map(
            fn ($enrollment): Grid => Grid::make(12)->components([
                Text::make($enrollment->student->full_name)->weight('bold')->columnSpan(4),
                Text::make(fn (): string => ($average = $results->subjectAverage($enrollment->id, $classSubject->id)) === null
                    ? 'Pas de note'
                    : number_format($average, 2, ',', '').' / 20')->columnSpan(2),
                TextInput::make("comments.{$enrollment->id}")
                    ->hiddenLabel()
                    ->placeholder('Appréciation')
                    ->maxLength(500)
                    ->disabled($readOnly)
                    ->columnSpan(6),
            ]),
            $results->enrollments,
        ));
    }

    private function loadComments(): void
    {
        $classSubject = $this->classSubject();
        $term = $this->term();

        $this->data['comments'] = $classSubject === null || $term === null
            ? []
            : TermComment::query()
                ->where('term_id', $term->id)
                ->where('class_subject_id', $classSubject->id)
                ->pluck('body', 'enrollment_id')
                ->all();
    }

    private function classSubject(): ?ClassSubject
    {
        $id = $this->data['class_subject_id'] ?? null;

        return blank($id) ? null : GradableClassSubjects::for($this->user())->with('schoolClass')->find($id);
    }

    private function term(): ?Term
    {
        $id = $this->data['term_id'] ?? null;

        return blank($id) ? null : Term::query()->find($id);
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Filament::auth()->user();

        return $user;
    }
}
