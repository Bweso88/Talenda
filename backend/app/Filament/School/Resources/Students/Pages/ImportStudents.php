<?php

namespace App\Filament\School\Resources\Students\Pages;

use App\Filament\School\Resources\Students\StudentResource;
use App\Modules\Academic\Enums\SchoolYearStatus;
use App\Modules\Academic\Models\SchoolYear;
use App\Modules\Core\Exceptions\BusinessRuleViolation;
use App\Modules\Enrollments\Actions\ImportStudents as ImportStudentsAction;
use App\Modules\Enrollments\Import\StudentImportReport;
use App\Modules\Enrollments\Import\StudentImportTemplate;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Import des élèves depuis Excel : téléchargement du modèle, analyse du
 * fichier (toutes les erreurs affichées d'un coup), puis import tout ou
 * rien.
 *
 * @property-read Schema $form
 */
class ImportStudents extends Page
{
    protected static string $resource = StudentResource::class;

    protected static ?string $title = 'Importer des élèves depuis Excel';

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    /**
     * Résultat de la dernière analyse.
     *
     * @var list<string>
     */
    public array $errorMessages = [];

    public ?int $readyCount = null;

    public static function canAccess(array $parameters = []): bool
    {
        return StudentResource::canCreate();
    }

    public function mount(): void
    {
        $this->form->fill(['school_year_id' => SchoolYear::query()->open()->value('id')]);
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
                Section::make('1. Préparer le fichier')
                    ->description('Téléchargez le modèle, remplissez une ligne par élève (les classes doivent déjà exister), puis enregistrez-le en .xlsx ou .csv.')
                    ->components([
                        Actions::make([
                            Action::make('template')
                                ->label('Télécharger le modèle Excel')
                                ->icon(Heroicon::OutlinedArrowDownTray)
                                ->action(fn (): BinaryFileResponse => $this->downloadTemplate()),
                        ]),
                    ]),
                Section::make('2. Analyser le fichier')
                    ->columns(2)
                    ->components([
                        Select::make('school_year_id')
                            ->label('Année scolaire')
                            ->options(fn (): array => SchoolYear::query()
                                ->where('status', '!=', SchoolYearStatus::Closed)
                                ->orderByDesc('starts_on')
                                ->pluck('name', 'id')
                                ->all())
                            ->required(),
                        FileUpload::make('file')
                            ->label('Fichier des élèves')
                            ->acceptedFileTypes([
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'text/csv',
                                'text/plain',
                                'application/vnd.ms-excel',
                            ])
                            ->maxSize(5120)
                            ->storeFiles(false)
                            ->required(),
                        Actions::make([
                            Action::make('analyze')
                                ->label('Analyser')
                                ->icon(Heroicon::OutlinedMagnifyingGlass)
                                ->action(fn () => $this->analyze()),
                        ]),
                    ]),
                Section::make('3. Résultat')
                    ->visible(fn (): bool => $this->readyCount !== null || $this->errorMessages !== [])
                    ->components([
                        Text::make(fn (): string => $this->errorMessages === []
                            ? "{$this->readyCount} élève(s) prêt(s) à être inscrit(s). Aucune erreur."
                            : count($this->errorMessages).' erreur(s) à corriger dans le fichier. Rien n\'a été importé.')
                            ->color(fn (): string => $this->errorMessages === [] ? 'success' : 'danger'),
                        Text::make(fn (): string => implode("\n", $this->errorMessages))
                            ->visible(fn (): bool => $this->errorMessages !== []),
                        Actions::make([
                            Action::make('import')
                                ->label(fn (): string => "Importer {$this->readyCount} élève(s)")
                                ->icon(Heroicon::OutlinedCheck)
                                ->color('success')
                                ->requiresConfirmation()
                                ->visible(fn (): bool => $this->errorMessages === [] && $this->readyCount > 0)
                                ->action(fn () => $this->import()),
                        ]),
                    ]),
            ]);
    }

    public function analyze(): void
    {
        $this->errorMessages = [];
        $this->readyCount = null;

        try {
            $report = $this->buildReport();
        } catch (BusinessRuleViolation $exception) {
            $this->errorMessages = [$exception->getMessage()];

            return;
        }

        $this->errorMessages = $report->errorMessages();
        $this->readyCount = count($report->rows);
    }

    public function import(): void
    {
        try {
            $count = app(ImportStudentsAction::class)->import($this->buildReport(), Filament::auth()->user());
        } catch (BusinessRuleViolation $exception) {
            Notification::make()->danger()->title($exception->getMessage())->send();

            return;
        }

        Notification::make()->success()->title("{$count} élève(s) importé(s) et inscrit(s).")->send();

        $this->redirect(StudentResource::getUrl('index'));
    }

    /**
     * Le fichier est relu à l'import : ce qui est importé est exactement
     * ce qui vient d'être validé, même si la base a changé entre-temps.
     */
    private function buildReport(): StudentImportReport
    {
        $state = $this->form->getState();
        $file = collect((array) ($this->data['file'] ?? []))->first();

        if (! $file instanceof TemporaryUploadedFile) {
            throw new BusinessRuleViolation('Choisissez un fichier.');
        }

        // Livewire efface les fichiers temporaires anciens : page restée
        // ouverte trop longtemps.
        if (! $file->exists()) {
            throw new BusinessRuleViolation('Le fichier n\'est plus disponible : choisissez-le à nouveau.');
        }

        return app(ImportStudentsAction::class)->analyze(
            $file->getRealPath(),
            $file->getClientOriginalExtension(),
            SchoolYear::query()->findOrFail($state['school_year_id']),
        );
    }

    private function downloadTemplate(): BinaryFileResponse
    {
        $path = Storage::disk('local')->path('tmp/modele-import-eleves-'.uniqid().'.xlsx');
        Storage::disk('local')->makeDirectory('tmp');

        app(StudentImportTemplate::class)->writeTo($path);

        return response()->download($path, 'modele-import-eleves.xlsx')->deleteFileAfterSend();
    }
}
