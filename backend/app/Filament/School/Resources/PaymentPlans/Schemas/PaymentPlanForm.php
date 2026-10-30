<?php

namespace App\Filament\School\Resources\PaymentPlans\Schemas;

use App\Modules\Academic\Enums\SchoolYearStatus;
use App\Modules\Academic\Models\SchoolYear;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

/**
 * Plan de paiement : échéances de la scolarité et part de chacune. Les
 * frais « à l'inscription » ne sont pas concernés.
 */
class PaymentPlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make()
                    ->columns(3)
                    ->components([
                        Select::make('school_year_id')
                            ->label('Année scolaire')
                            ->relationship('schoolYear', 'name', fn (Builder $query) => $query
                                ->where('status', '!=', SchoolYearStatus::Closed)
                                ->orderByDesc('starts_on'))
                            ->default(fn (): ?int => SchoolYear::query()->open()->value('id'))
                            ->required()
                            ->disabledOn('edit'),
                        TextInput::make('name')
                            ->label('Nom')
                            ->placeholder('Mensuel')
                            ->required()
                            ->maxLength(50),
                        Toggle::make('is_default')
                            ->label('Plan par défaut')
                            ->helperText('Proposé automatiquement aux nouvelles factures de l\'année.')
                            ->inline(false),
                    ]),
                Section::make('Échéances de la scolarité')
                    ->description('La somme des parts doit faire 100 %. Les montants sont arrondis au franc, l\'arrondi va à la dernière échéance.')
                    ->components([
                        Repeater::make('installments')
                            ->hiddenLabel()
                            ->relationship()
                            ->orderColumn('position')
                            ->columns(3)
                            ->addActionLabel('Ajouter une échéance')
                            ->minItems(1)
                            ->schema([
                                TextInput::make('label')
                                    ->label('Libellé')
                                    ->placeholder('Octobre')
                                    ->required()
                                    ->maxLength(50),
                                DatePicker::make('due_on')
                                    ->label('Date limite')
                                    ->required(),
                                TextInput::make('percentage')
                                    ->label('Part de la scolarité')
                                    ->numeric()
                                    ->minValue(0.01)
                                    ->maxValue(100)
                                    ->step(0.01)
                                    ->suffix('%')
                                    ->required(),
                            ])
                            ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                $total = round(collect((array) $value)->sum(fn (array $item): float => (float) ($item['percentage'] ?? 0)), 2);

                                if ($total !== 100.0) {
                                    $fail("La somme des parts fait {$total} % au lieu de 100 %.");
                                }
                            }),
                    ]),
            ]);
    }
}
