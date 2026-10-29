<?php

namespace App\Filament\School\Pages;

use App\Filament\School\Support\NavigationGroups;
use App\Modules\Assessments\Enums\AbsencePolicy;
use App\Modules\Assessments\Models\GradingSetting;
use App\Modules\Core\Actions\RecordAudit;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Règles de notation de l'école : barème, moyenne de passage, absences,
 * mentions. S'appliquent aux prochains calculs ; les bulletins déjà publiés
 * ne changent pas.
 *
 * @property-read Schema $form
 */
class GradingSettingsPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroups::SETTINGS;

    protected static ?int $navigationSort = 60;

    protected static ?string $slug = 'notation';

    protected static ?string $title = 'Règles de notation';

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
        $settings = GradingSetting::current();

        $this->form->fill([
            'max_score' => $settings->max_score,
            'passing_average' => $settings->passing_average,
            'absence_policy' => $settings->absence_policy->value,
            'distinctions' => $settings->distinctions,
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
                    ->columns(3)
                    ->components([
                        TextInput::make('max_score')->label('Barème des moyennes')->numeric()->integer()->minValue(1)->maxValue(100)->suffix('points')->required(),
                        TextInput::make('passing_average')->label('Moyenne de passage')->numeric()->minValue(0)->required(),
                        Select::make('absence_policy')->label('Élève absent à une évaluation')->options(AbsencePolicy::class)->required(),
                    ]),
                Section::make('Mentions')
                    ->description('Attribuées sur la moyenne du trimestre, du seuil le plus haut au plus bas.')
                    ->components([
                        Repeater::make('distinctions')
                            ->hiddenLabel()
                            ->columns(2)
                            ->addActionLabel('Ajouter une mention')
                            ->schema([
                                TextInput::make('name')->label('Mention')->required()->maxLength(50),
                                TextInput::make('min')->label('À partir de')->numeric()->minValue(0)->required(),
                            ]),
                    ]),
            ]);
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Enregistrer')
                ->icon(Heroicon::OutlinedCheck)
                ->action(function (): void {
                    $state = $this->form->getState();
                    $distinctions = array_map(
                        fn (array $item): array => ['name' => (string) $item['name'], 'min' => (float) $item['min']],
                        array_values(is_array($state['distinctions'] ?? null) ? $state['distinctions'] : []),
                    );
                    usort($distinctions, fn (array $a, array $b): int => $b['min'] <=> $a['min']);

                    GradingSetting::current()->update([...$state, 'distinctions' => $distinctions]);
                    app(RecordAudit::class)->handle('grading.settings_updated', actor: Filament::auth()->user());

                    Notification::make()->success()->title('Règles de notation enregistrées.')->send();
                }),
        ];
    }
}
