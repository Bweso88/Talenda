<?php

namespace App\Filament\School\Resources\Announcements\Pages;

use App\Filament\School\Resources\Announcements\AnnouncementResource;
use App\Models\User;
use App\Modules\Communication\Actions\PublishAnnouncement;
use App\Modules\Communication\Enums\AnnouncementAudience;
use App\Modules\Communication\Models\Announcement;
use App\Modules\Core\Exceptions\BusinessRuleViolation;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Exceptions\Halt;

class ManageAnnouncements extends ManageRecords
{
    protected static string $resource = AnnouncementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nouvelle annonce')
                ->modalHeading('Nouvelle annonce aux familles')
                ->modalSubmitActionLabel('Publier')
                ->createAnother(false)
                ->successNotification(null)
                ->using(function (array $data): Announcement {
                    /** @var User $user */
                    $user = Filament::auth()->user();
                    $audience = $data['audience'] instanceof AnnouncementAudience ? $data['audience'] : AnnouncementAudience::from($data['audience']);

                    try {
                        $announcement = app(PublishAnnouncement::class)->handle(
                            (string) $data['title'],
                            (string) $data['body'],
                            $audience,
                            filled($data['audience_id'] ?? null) ? (int) $data['audience_id'] : null,
                            (bool) ($data['send_sms'] ?? false),
                            $user,
                        );
                    } catch (BusinessRuleViolation $exception) {
                        Notification::make()->danger()->title($exception->getMessage())->send();

                        throw new Halt;
                    }

                    Notification::make()->success()
                        ->title('Annonce publiée')
                        ->body("{$announcement->app_recipients_count} parent(s) prévenu(s) dans l'app".($announcement->send_sms ? ", {$announcement->sms_count} SMS envoyé(s)." : '.'))
                        ->send();

                    return $announcement;
                }),
        ];
    }
}
