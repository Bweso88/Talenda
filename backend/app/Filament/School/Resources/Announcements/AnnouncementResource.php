<?php

namespace App\Filament\School\Resources\Announcements;

use App\Filament\School\Resources\Announcements\Pages\ManageAnnouncements;
use App\Filament\School\Support\NavigationGroups;
use App\Filament\Support\FrenchLabels;
use App\Modules\Academic\Models\Cycle;
use App\Modules\Academic\Models\Level;
use App\Modules\Academic\Models\SchoolClass;
use App\Modules\Academic\Models\SchoolYear;
use App\Modules\Communication\Actions\PublishAnnouncement;
use App\Modules\Communication\Enums\AnnouncementAudience;
use App\Modules\Communication\Models\Announcement;
use App\Modules\Communication\Models\UserNotification;
use App\Modules\Communication\Support\AnnouncementAudienceQuery;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Annonces de l'école aux familles (réunion, fermeture, examen…),
 * publiées immédiatement dans l'app et, si on le demande, par SMS.
 */
class AnnouncementResource extends Resource
{
    use FrenchLabels;

    protected static ?string $model = Announcement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroups::COMMUNICATION;

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'annonces';

    protected static ?string $modelLabel = 'annonce';

    protected static ?string $pluralModelLabel = 'annonces';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('title')->label('Titre')->required()->maxLength(150)->live(onBlur: true)->columnSpanFull()
                    ->placeholder('Ex. Réunion des parents du 1er trimestre'),
                Textarea::make('body')->label('Message')->required()->maxLength(2000)->rows(4)->live(onBlur: true)->columnSpanFull(),
                Select::make('audience')
                    ->label('Destinataires')
                    ->options(AnnouncementAudience::class)
                    ->default(AnnouncementAudience::School->value)
                    ->selectablePlaceholder(false)
                    ->required()
                    ->live(),
                Select::make('audience_id')
                    ->label(fn (Get $get): string => match (self::audience($get('audience'))) {
                        AnnouncementAudience::Cycle => 'Cycle',
                        AnnouncementAudience::Level => 'Niveau',
                        default => 'Classe',
                    })
                    ->options(fn (Get $get): array => self::targets(self::audience($get('audience'))))
                    ->visible(fn (Get $get): bool => self::audience($get('audience')) !== AnnouncementAudience::School)
                    ->required(fn (Get $get): bool => self::audience($get('audience')) !== AnnouncementAudience::School)
                    ->live(),
                Toggle::make('send_sms')
                    ->label('Envoyer aussi par SMS (un par famille)')
                    ->helperText('Pour toucher les familles sans smartphone. Chaque SMS est facturé.')
                    ->live()
                    ->columnSpanFull(),
                Text::make(fn (Get $get): string => self::estimate($get))->color('gray')->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('published_at', 'desc')
            ->columns([
                TextColumn::make('published_at')->label('Publiée le')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('title')->label('Titre')->weight('bold')->description(fn (Announcement $record): string => mb_strimwidth($record->body, 0, 90, '…')),
                TextColumn::make('audience_label')->label('Destinataires'),
                TextColumn::make('app_recipients_count')->label('Lue dans l\'app')
                    ->state(fn (Announcement $record): string => self::readCount($record)." / {$record->app_recipients_count}"),
                IconColumn::make('send_sms')->label('SMS')->boolean(),
                TextColumn::make('sms_count')->label('SMS facturés')->alignCenter(),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAnnouncements::route('/'),
        ];
    }

    /**
     * @return array<int, string>
     */
    private static function targets(AnnouncementAudience $audience): array
    {
        return match ($audience) {
            AnnouncementAudience::Cycle => Cycle::query()->orderBy('position')->pluck('name', 'id')->all(),
            AnnouncementAudience::Level => Level::query()->orderBy('position')->pluck('name', 'id')->all(),
            AnnouncementAudience::SchoolClass => SchoolClass::query()
                ->where('school_year_id', SchoolYear::query()->open()->value('id'))
                ->orderBy('name')
                ->pluck('name', 'id')
                ->all(),
            AnnouncementAudience::School => [],
        };
    }

    private static function audience(mixed $state): AnnouncementAudience
    {
        return $state instanceof AnnouncementAudience ? $state : (AnnouncementAudience::tryFrom((string) $state) ?? AnnouncementAudience::School);
    }

    private static function estimate(Get $get): string
    {
        $audience = self::audience($get('audience'));
        $target = filled($get('audience_id')) ? (int) $get('audience_id') : null;

        if ($audience !== AnnouncementAudience::School && $target === null) {
            return 'Choisissez les destinataires.';
        }

        $estimate = AnnouncementAudienceQuery::estimate($audience, $target, PublishAnnouncement::smsText((string) $get('title'), (string) $get('body')));

        return "{$estimate['families']} famille(s), dont {$estimate['app_users']} parent(s) dans l'app."
            .($get('send_sms') ? " Envoi par SMS : {$estimate['sms']} SMS facturé(s)." : '');
    }

    private static function readCount(Announcement $record): int
    {
        return UserNotification::query()
            ->where('data->announcement', $record->uuid)
            ->whereNotNull('read_at')
            ->count();
    }
}
