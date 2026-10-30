<?php

namespace App\Filament\School\Resources\Assessments\Pages;

use App\Filament\School\Resources\Assessments\AssessmentResource;
use App\Modules\Assessments\Actions\SaveScores;
use App\Modules\Assessments\Enums\ScoreStatus;
use App\Modules\Assessments\Models\Assessment;
use App\Modules\Assessments\Models\Score;
use App\Modules\Assessments\Support\ComputeClassTermResults;
use App\Modules\Core\Exceptions\BusinessRuleViolation;
use App\Modules\Enrollments\Models\Enrollment;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;

/**
 * Grille de saisie des notes d'une évaluation : une ligne par élève,
 * navigation au clavier (Tab), enregistrement de toute la classe d'un coup.
 *
 * @property-read Schema $form
 */
class EnterScores extends Page
{
    use InteractsWithRecord;

    protected static string $resource = AssessmentResource::class;

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        abort_unless(Filament::auth()->user()?->can('view', $this->getRecord()) ?? false, 403);

        $scores = $this->assessment()->scores()->get()->keyBy('enrollment_id');

        $this->form->fill([
            'scores' => $this->enrollments()->mapWithKeys(function (Enrollment $enrollment) use ($scores): array {
                /** @var Score|null $score */
                $score = $scores->get($enrollment->id);

                return [$enrollment->id => [
                    'value' => $score?->value === null ? null : rtrim(rtrim((string) $score->value, '0'), '.'),
                    'status' => $score === null ? ScoreStatus::Present->value : $score->status->value,
                    'comment' => $score?->comment,
                ]];
            })->all(),
        ]);
    }

    public function getTitle(): string|Htmlable
    {
        $assessment = $this->assessment();

        return "{$assessment->title} — {$assessment->classSubject->schoolClass->name}";
    }

    public function getSubheading(): ?string
    {
        $assessment = $this->assessment();
        $max = rtrim(rtrim((string) $assessment->max_score, '0'), '.');

        return "{$assessment->classSubject->subject->name} · {$assessment->assessmentType->name} du {$assessment->held_on->format('d/m/Y')} · noté sur {$max} · {$assessment->term->name}"
            .($this->canEdit() ? '' : ' · saisie fermée (lecture seule)');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedSchema::make('form')]);
    }

    public function form(Schema $schema): Schema
    {
        $readOnly = ! $this->canEdit();
        $max = (float) $this->assessment()->max_score;

        return $schema
            ->statePath('data')
            ->components([
                Section::make()
                    ->components($this->enrollments()->map(fn (Enrollment $enrollment, int $index): Grid => Grid::make(12)->components([
                        Text::make(($index + 1).'. '.$enrollment->student->full_name)
                            ->weight('bold')
                            ->columnSpan(4),
                        TextInput::make("scores.{$enrollment->id}.value")
                            ->hiddenLabel()
                            ->placeholder('Note')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue($max)
                            ->step(0.25)
                            ->disabled($readOnly)
                            ->columnSpan(2),
                        Select::make("scores.{$enrollment->id}.status")
                            ->hiddenLabel()
                            ->options(ScoreStatus::class)
                            ->selectablePlaceholder(false)
                            ->disabled($readOnly)
                            ->columnSpan(2),
                        TextInput::make("scores.{$enrollment->id}.comment")
                            ->hiddenLabel()
                            ->placeholder('Remarque (facultatif)')
                            ->maxLength(255)
                            ->disabled($readOnly)
                            ->columnSpan(4),
                    ]))->all()),
            ]);
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Enregistrer les notes')
                ->icon(Heroicon::OutlinedCheck)
                ->visible(fn (): bool => $this->canEdit())
                ->action(fn () => $this->save()),
        ];
    }

    public function save(): void
    {
        $state = $this->form->getState();

        try {
            $saved = app(SaveScores::class)->handle($this->assessment(), $state['scores'] ?? []);
        } catch (BusinessRuleViolation $exception) {
            Notification::make()->danger()->title($exception->getMessage())->send();

            return;
        }

        Notification::make()->success()->title("{$saved} note(s) enregistrée(s).")->send();
    }

    private function assessment(): Assessment
    {
        /** @var Assessment $assessment */
        $assessment = $this->getRecord();
        $assessment->loadMissing(['term', 'assessmentType', 'classSubject.schoolClass', 'classSubject.subject']);

        return $assessment;
    }

    /**
     * @return Collection<int, Enrollment>
     */
    private function enrollments(): Collection
    {
        $assessment = $this->assessment();

        return ComputeClassTermResults::enrollmentsOf($assessment->term, $assessment->classSubject->schoolClass);
    }

    private function canEdit(): bool
    {
        return Filament::auth()->user()?->can('update', $this->assessment()) ?? false;
    }
}
