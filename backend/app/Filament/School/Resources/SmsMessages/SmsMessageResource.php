<?php

namespace App\Filament\School\Resources\SmsMessages;

use App\Filament\School\Resources\SmsMessages\Pages\ListSmsMessages;
use App\Filament\School\Support\NavigationGroups;
use App\Filament\Support\FrenchLabels;
use App\Modules\Communication\Enums\SmsPurpose;
use App\Modules\Communication\Enums\SmsStatus;
use App\Modules\Communication\Jobs\DeliverSms;
use App\Modules\Communication\Models\CommunicationSetting;
use App\Modules\Communication\Models\SmsMessage;
use App\Modules\Core\Support\PhoneNumber;
use App\Modules\Core\Tenancy\TenantContext;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Journal des SMS de l'école : ce qui a été envoyé, à qui, pour quoi, et
 * combien de SMS ont été facturés.
 */
class SmsMessageResource extends Resource
{
    use FrenchLabels;

    protected static ?string $model = SmsMessage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDevicePhoneMobile;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroups::COMMUNICATION;

    protected static ?int $navigationSort = 20;

    protected static ?string $slug = 'journal-sms';

    protected static ?string $navigationLabel = 'Journal SMS';

    protected static ?string $modelLabel = 'SMS';

    protected static ?string $pluralModelLabel = 'journal SMS';

    public static function canViewAny(): bool
    {
        return Filament::auth()->user()?->can('communication.manage') ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->heading(fn (): string => 'Ce mois-ci : '.SmsMessage::query()
                ->where('created_at', '>=', now()->startOfMonth())
                ->where('status', '!=', SmsStatus::Failed)
                ->sum('segments').' SMS facturé(s)')
            ->columns([
                TextColumn::make('created_at')->label('Date')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('phone')->label('Téléphone')->formatStateUsing(fn (string $state): ?string => PhoneNumber::format($state))->searchable(),
                TextColumn::make('purpose')->label('Objet'),
                TextColumn::make('body')->label('Message')->limit(42)->tooltip(fn (SmsMessage $record): string => $record->body),
                TextColumn::make('segments')->label('SMS')->alignCenter(),
                TextColumn::make('status')->label('Statut')->badge()
                    ->description(fn (SmsMessage $record): ?string => $record->error),
            ])
            ->filters([
                SelectFilter::make('status')->label('Statut')->options(SmsStatus::class),
                SelectFilter::make('purpose')->label('Objet')->options(SmsPurpose::class),
            ])
            ->recordActions([
                Action::make('retry')
                    ->label('Renvoyer')
                    ->icon(Heroicon::OutlinedArrowPath)
                    // Le texte d'un SMS d'accès est masqué dans le journal : on rouvre l'accès à la place.
                    ->visible(fn (SmsMessage $record): bool => $record->status === SmsStatus::Failed && $record->purpose !== SmsPurpose::Credentials)
                    ->action(function (SmsMessage $record): void {
                        $record->update(['status' => SmsStatus::Queued, 'error' => null]);
                        DeliverSms::dispatch(
                            (int) app(TenantContext::class)->id(),
                            $record->id,
                            CommunicationSetting::current()->sms_sender ?: (string) config('talenda.sms.default_sender'),
                        );

                        Notification::make()->success()->title('SMS renvoyé.')->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSmsMessages::route('/'),
        ];
    }
}
