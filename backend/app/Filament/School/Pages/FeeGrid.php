<?php

namespace App\Filament\School\Pages;

use App\Filament\School\Support\NavigationGroups;
use App\Modules\Academic\Enums\SchoolYearStatus;
use App\Modules\Academic\Models\Level;
use App\Modules\Academic\Models\SchoolYear;
use App\Modules\Core\Actions\RecordAudit;
use App\Modules\Finances\Models\FeeAmount;
use App\Modules\Finances\Models\FeeType;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use UnitEnum;

/**
 * Grille tarifaire d'une année : un montant par niveau et par type de
 * frais, saisie en un seul écran. Les factures déjà émises ne changent
 * pas ; « Appliquer la grille » (factures) complète celles qui n'avaient
 * pas encore un frais.
 *
 * @property-read Schema $form
 */
class FeeGrid extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroups::FINANCE_SETTINGS;

    protected static ?int $navigationSort = 20;

    protected static ?string $slug = 'grille-tarifaire';

    protected static ?string $title = 'Grille tarifaire';

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    public static function canAccess(): bool
    {
        return Filament::auth()->user()?->can('finance.view') ?? false;
    }

    public function mount(): void
    {
        $this->loadYear(SchoolYear::query()->open()->value('id') ?? SchoolYear::query()->latest('starts_on')->value('id'));
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedSchema::make('form')]);
    }

    public function form(Schema $schema): Schema
    {
        $feeTypes = FeeType::query()->orderBy('position')->get();
        $canEdit = $this->canEdit();

        $levels = Level::query()->with('cycle')->orderBy('position')->get()->map(
            fn (Level $level): Fieldset => Fieldset::make("{$level->name} ({$level->cycle->name})")
                ->columns(max(1, $feeTypes->count()))
                ->schema($feeTypes->map(
                    fn (FeeType $feeType): TextInput => TextInput::make("amounts.{$level->id}.{$feeType->id}")
                        ->label($feeType->name)
                        ->numeric()
                        ->integer()
                        ->minValue(1)
                        ->suffix('FCFA')
                        ->disabled(! $canEdit),
                )->all()),
        );

        return $schema
            ->statePath('data')
            ->components([
                Section::make()
                    ->components([
                        Select::make('school_year_id')
                            ->label('Année scolaire')
                            ->options(fn (): array => SchoolYear::query()->orderByDesc('starts_on')->pluck('name', 'id')->all())
                            ->live()
                            ->afterStateUpdated(fn (?string $state) => $this->loadYear($state === null ? null : (int) $state))
                            ->required(),
                    ]),
                ...$levels->all(),
            ]);
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Enregistrer la grille')
                ->icon(Heroicon::OutlinedCheck)
                ->visible(fn (): bool => $this->canEdit())
                ->action(fn () => $this->save()),
        ];
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $schoolYearId = (int) $state['school_year_id'];

        if (SchoolYear::query()->findOrFail($schoolYearId)->status === SchoolYearStatus::Closed) {
            Notification::make()->danger()->title('Cette année est clôturée.')->send();

            return;
        }

        DB::transaction(function () use ($state, $schoolYearId): void {
            foreach ((array) ($state['amounts'] ?? []) as $levelId => $amounts) {
                foreach ((array) $amounts as $feeTypeId => $amount) {
                    $keys = ['school_year_id' => $schoolYearId, 'level_id' => (int) $levelId, 'fee_type_id' => (int) $feeTypeId];

                    if (blank($amount)) {
                        FeeAmount::query()->where($keys)->delete();
                    } else {
                        FeeAmount::query()->updateOrCreate($keys, ['amount' => (int) $amount]);
                    }
                }
            }

            app(RecordAudit::class)->handle('finance.fee_grid_updated', actor: Filament::auth()->user(), metadata: ['school_year_id' => $schoolYearId]);
        });

        Notification::make()->success()->title('Grille tarifaire enregistrée.')->send();
    }

    private function loadYear(?int $schoolYearId): void
    {
        $amounts = [];

        FeeAmount::query()
            ->where('school_year_id', $schoolYearId)
            ->get()
            ->each(function (FeeAmount $feeAmount) use (&$amounts): void {
                $amounts[$feeAmount->level_id][$feeAmount->fee_type_id] = $feeAmount->amount;
            });

        $this->form->fill(['school_year_id' => $schoolYearId, 'amounts' => $amounts]);
    }

    private function canEdit(): bool
    {
        return Filament::auth()->user()?->can('finance.manage') ?? false;
    }
}
