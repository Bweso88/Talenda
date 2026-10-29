<?php

namespace App\Filament\School\Pages;

use App\Filament\School\Resources\Invoices\InvoiceResource;
use App\Filament\School\Support\NavigationGroups;
use App\Models\User;
use App\Modules\Academic\Models\SchoolClass;
use App\Modules\Academic\Models\SchoolYear;
use App\Modules\Communication\Actions\SendPaymentReminders;
use App\Modules\Core\Support\PhoneNumber;
use App\Modules\Finances\Support\Money;
use App\Modules\Finances\Support\UnpaidReport as UnpaidReportBuilder;
use App\Modules\Finances\Support\UnpaidRow;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use UnitEnum;

/**
 * État des impayés : élèves ayant une échéance passée non réglée, avec le
 * téléphone du responsable qui paie, pour les relances. Export Excel.
 */
class UnpaidReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroups::FINANCES;

    protected static ?int $navigationSort = 30;

    protected static ?string $slug = 'impayes';

    protected static ?string $title = 'Impayés';

    public static function canAccess(): bool
    {
        return Filament::auth()->user()?->can('finance.view') ?? false;
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        $money = fn (int $amount): string => Money::format($amount);

        return $table
            ->records(fn (array $filters): array => $this->rows($filters['school_class_id']['value'] ?? null)
                ->mapWithKeys(fn (UnpaidRow $row): array => [$row->invoice->id => $row->toArray()])
                ->all())
            ->heading(fn (): string => 'Total en retard : '.$money((int) $this->rows($this->tableFilters['school_class_id']['value'] ?? null)->sum(fn (UnpaidRow $row): int => $row->overdue)))
            ->paginated([25, 50, 100])
            ->recordUrl(fn (array $record): string => InvoiceResource::getUrl('view', ['record' => $record['invoice_id']]))
            ->columns([
                TextColumn::make('class')->label('Classe'),
                TextColumn::make('student')->label('Élève')->weight('bold'),
                TextColumn::make('registration_number')->label('Matricule'),
                TextColumn::make('contact')->label('Responsable')->placeholder('—'),
                TextColumn::make('phone')->label('Téléphone')->placeholder('—')
                    ->formatStateUsing(fn (?string $state): ?string => PhoneNumber::format($state)),
                TextColumn::make('paid')->label('Payé')->alignEnd()->formatStateUsing(fn (int $state): string => $money($state)),
                TextColumn::make('balance')->label('Reste à payer')->alignEnd()->formatStateUsing(fn (int $state): string => $money($state)),
                TextColumn::make('overdue')->label('En retard')->alignEnd()->weight('bold')->color('danger')
                    ->formatStateUsing(fn (int $state): string => $money($state)),
            ])
            ->toolbarActions([
                BulkAction::make('remind')
                    ->label('Relancer par SMS')
                    ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                    ->visible(fn (): bool => $this->canRemind())
                    ->requiresConfirmation()
                    ->modalDescription('Un SMS (et une notification dans l\'app) au responsable qui paie, pour chaque élève sélectionné. Une famille déjà relancée aujourd\'hui ne l\'est pas une seconde fois.')
                    ->action(fn (Collection $records) => $this->remind($records->pluck('invoice_id')->map(fn ($id): int => (int) $id)->all())),
            ])
            ->filters([
                SelectFilter::make('school_class_id')
                    ->label('Classe')
                    ->options(fn (): array => SchoolClass::query()
                        ->where('school_year_id', SchoolYear::query()->open()->value('id'))
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all()),
            ]);
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('remindAll')
                ->label('Relancer toutes les familles')
                ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                ->color('warning')
                ->visible(fn (): bool => $this->canRemind())
                ->requiresConfirmation()
                ->modalHeading('Relancer toutes les familles en retard ?')
                ->modalDescription(fn (): string => $this->rows($this->tableFilters['school_class_id']['value'] ?? null)->count().' famille(s) recevront un SMS de rappel (classe filtrée seulement, le cas échéant). Une famille déjà relancée aujourd\'hui ne l\'est pas une seconde fois.')
                ->action(fn () => $this->remind(null)),
            Action::make('export')
                ->label('Exporter en Excel')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->action(fn (): BinaryFileResponse => $this->export()),
        ];
    }

    /**
     * @return Collection<int, UnpaidRow>
     */
    private function rows(mixed $schoolClassId): Collection
    {
        $schoolYear = SchoolYear::query()->open()->first();

        if ($schoolYear === null) {
            return collect();
        }

        return app(UnpaidReportBuilder::class)->rows($schoolYear, filled($schoolClassId) ? (int) $schoolClassId : null);
    }

    /**
     * @param  list<int>|null  $invoiceIds  null = toutes les lignes affichées
     */
    private function remind(?array $invoiceIds): void
    {
        $rows = $this->rows($this->tableFilters['school_class_id']['value'] ?? null)
            ->when($invoiceIds !== null, fn (Collection $rows) => $rows->filter(fn (UnpaidRow $row): bool => in_array($row->invoice->id, $invoiceIds, true)));

        /** @var User $user */
        $user = Filament::auth()->user();
        $result = app(SendPaymentReminders::class)->handle($rows, $user);

        Notification::make()->success()
            ->title("{$result['sent']} famille(s) relancée(s) par SMS")
            ->body($result['skipped'] > 0 ? "{$result['skipped']} non relancée(s) : déjà relancée(s) aujourd'hui ou sans téléphone." : null)
            ->send();
    }

    private function canRemind(): bool
    {
        return Filament::auth()->user()?->can('finance.manage') ?? false;
    }

    private function export(): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'impayes').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(['Classe', 'Élève', 'Matricule', 'Responsable', 'Téléphone', 'Total', 'Payé', 'Reste à payer', 'En retard'], (new Style)->setFontBold()));

        foreach ($this->rows($this->tableFilters['school_class_id']['value'] ?? null) as $row) {
            $writer->addRow(Row::fromValues([
                $row->class, $row->student, $row->registrationNumber, $row->contact ?? '',
                PhoneNumber::format($row->phone) ?? '', $row->total, $row->paid, $row->balance, $row->overdue,
            ]));
        }

        $writer->close();

        return response()->download($path, 'impayes-'.now()->format('Y-m-d').'.xlsx')->deleteFileAfterSend();
    }
}
