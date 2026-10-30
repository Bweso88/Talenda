<?php

declare(strict_types=1);

namespace App\Modules\Communication;

use App\Modules\Assessments\Events\TermPublished;
use App\Modules\Communication\Listeners\NotifyFamilies;
use App\Modules\Communication\Push\LogPushGateway;
use App\Modules\Communication\Push\PushGateway;
use App\Modules\Communication\Sms\LogSmsGateway;
use App\Modules\Communication\Sms\SmsGateway;
use App\Modules\Finances\Events\PaymentRecorded;
use App\Modules\SchoolLife\Events\AbsencesRecorded;
use App\Modules\SchoolLife\Events\JustificationReviewed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

/**
 * Module Communication : fournisseurs SMS et push (choisis par la
 * configuration), messages automatiques aux familles sur les événements
 * des autres modules.
 */
final class CommunicationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SmsGateway::class, fn () => match (config('talenda.sms.driver')) {
            'log' => new LogSmsGateway,
            default => throw new InvalidArgumentException('Pilote SMS inconnu : '.config('talenda.sms.driver')),
        });

        $this->app->singleton(PushGateway::class, fn () => match (config('talenda.push.driver')) {
            'log' => new LogPushGateway,
            default => throw new InvalidArgumentException('Pilote push inconnu : '.config('talenda.push.driver')),
        });
    }

    public function boot(): void
    {
        Event::listen(PaymentRecorded::class, [NotifyFamilies::class, 'paymentRecorded']);
        Event::listen(AbsencesRecorded::class, [NotifyFamilies::class, 'absencesRecorded']);
        Event::listen(TermPublished::class, [NotifyFamilies::class, 'termPublished']);
        Event::listen(JustificationReviewed::class, [NotifyFamilies::class, 'justificationReviewed']);
    }
}
