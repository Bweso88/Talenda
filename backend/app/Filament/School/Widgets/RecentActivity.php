<?php

namespace App\Filament\School\Widgets;

use App\Filament\School\Support\DashboardFigures;
use App\Models\User;
use App\Modules\Communication\Models\Announcement;
use App\Modules\Enrollments\Models\Enrollment;
use App\Modules\Finances\Models\Payment;
use App\Modules\Finances\Support\Money;
use App\Modules\SchoolLife\Enums\AttendanceStatus;
use App\Modules\SchoolLife\Enums\JustificationStatus;
use App\Modules\SchoolLife\Models\AbsenceJustification;
use App\Modules\SchoolLife\Models\AttendanceSession;
use Carbon\CarbonInterface;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;

/**
 * Fil d'activité : derniers paiements, appels, justificatifs, annonces et
 * inscriptions, selon les droits de la personne connectée.
 */
class RecentActivity extends Widget
{
    protected static ?int $sort = 6;

    protected string $view = 'filament.school.widgets.recent-activity';

    /**
     * @var int|string|array<string, int>
     */
    protected int|string|array $columnSpan = ['md' => 2, 'xl' => 2];

    public static function canView(): bool
    {
        $user = Filament::auth()->user();

        return collect(['finance.view', 'attendance.manage', 'communication.send', 'student.view'])->contains(fn (string $permission): bool => $user?->can($permission) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        /** @var User $user */
        $user = Filament::auth()->user();
        $events = [];

        if ($user->can('finance.view')) {
            foreach (Payment::query()->valid()->with(['invoice.student', 'invoice.enrollment.schoolClass'])->latest('id')->limit(5)->get() as $payment) {
                $events[] = self::event($payment->created_at, Heroicon::OutlinedBanknotes, 'success', 'Paiement reçu : '.Money::format($payment->amount), "{$payment->invoice->student->full_name} ({$payment->invoice->enrollment->schoolClass->name}) • {$payment->method->getLabel()}");
            }
        }

        if ($user->can('attendance.manage')) {
            foreach (AttendanceSession::query()->with(['schoolClass', 'slot', 'taker'])->withCount(['records as absent_count' => fn ($query) => $query->where('status', AttendanceStatus::Absent)])->latest('updated_at')->limit(3)->get() as $session) {
                $events[] = self::event($session->updated_at, Heroicon::OutlinedClipboardDocumentCheck, 'primary', "Appel : {$session->schoolClass->name}, {$session->slot->name}", "{$session->getAttribute('absent_count')} absent(s)".($session->taker ? " • {$session->taker->name}" : ''));
            }

            foreach (AbsenceJustification::query()->with('enrollment.student')->latest('id')->limit(5)->get() as $justification) {
                $events[] = self::event($justification->created_at, Heroicon::OutlinedDocumentCheck, $justification->status === JustificationStatus::Pending ? 'warning' : 'success', 'Justificatif : '.$justification->reason->getLabel(), "{$justification->enrollment->student->full_name} • {$justification->status->getLabel()}");
            }
        }

        if ($user->can('communication.send')) {
            foreach (Announcement::query()->latest('published_at')->limit(3)->get() as $announcement) {
                $events[] = self::event($announcement->published_at, Heroicon::OutlinedMegaphone, 'primary', "Annonce : {$announcement->title}", "{$announcement->audience_label} • {$announcement->app_recipients_count} parent(s) dans l'app");
            }
        }

        if ($user->can('student.view')) {
            foreach (Enrollment::query()->with(['student', 'schoolClass'])->latest('id')->limit(3)->get() as $enrollment) {
                $events[] = self::event($enrollment->created_at, Heroicon::OutlinedUserPlus, 'primary', 'Inscription : '.$enrollment->student->full_name, "{$enrollment->schoolClass->name} • {$enrollment->type->getLabel()}");
            }
        }

        usort($events, fn (array $a, array $b): int => $b['at'] <=> $a['at']);

        return ['events' => array_slice($events, 0, 8)];
    }

    /**
     * @return array{at: CarbonInterface, icon: Heroicon, tone: string, title: string, text: string, since: string}
     */
    private static function event(?CarbonInterface $at, Heroicon $icon, string $tone, string $title, string $text): array
    {
        $at ??= now();

        return ['at' => $at, 'icon' => $icon, 'tone' => $tone, 'title' => $title, 'text' => $text, 'since' => DashboardFigures::since($at)];
    }
}
