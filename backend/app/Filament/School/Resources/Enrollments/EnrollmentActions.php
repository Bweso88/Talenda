<?php

namespace App\Filament\School\Resources\Enrollments;

use App\Modules\Academic\Models\SchoolClass;
use App\Modules\Academic\Models\SchoolYear;
use App\Modules\Core\Exceptions\BusinessRuleViolation;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Enrollments\Actions\IssueDocuments;
use App\Modules\Enrollments\Actions\ManageEnrollment;
use App\Modules\Enrollments\Actions\ReEnrollStudent;
use App\Modules\Enrollments\Enums\EnrollmentStatus;
use App\Modules\Enrollments\Models\Enrollment;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Actions sur les inscriptions, partagées par la liste des inscriptions et
 * l'historique de la fiche élève. Toutes passent par les actions métier du
 * module Enrollments ; une règle non respectée devient une notification.
 */
final class EnrollmentActions
{
    public static function schoolCertificate(): Action
    {
        return Action::make('schoolCertificate')
            ->label('Certificat de scolarité')
            ->icon(Heroicon::OutlinedDocumentText)
            ->visible(fn (Enrollment $record): bool => $record->status === EnrollmentStatus::Enrolled && self::userCan('document.issue'))
            ->action(fn (Enrollment $record): ?StreamedResponse => self::download(
                fn (): string => app(IssueDocuments::class)->schoolCertificate($record, Filament::auth()->user()),
                "certificat-scolarite-{$record->student->registration_number}.pdf",
            ));
    }

    public static function confirm(): Action
    {
        return Action::make('confirm')
            ->label('Confirmer l\'inscription')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->requiresConfirmation()
            ->visible(fn (Enrollment $record): bool => $record->status === EnrollmentStatus::PreRegistered && self::canUpdate($record))
            ->action(self::guarded(fn (Enrollment $record) => app(ManageEnrollment::class)->confirm($record, Filament::auth()->user()), 'Inscription confirmée.'));
    }

    public static function changeClass(): Action
    {
        return Action::make('changeClass')
            ->label('Changer de classe')
            ->icon(Heroicon::OutlinedArrowsRightLeft)
            ->visible(fn (Enrollment $record): bool => $record->status->isActive() && self::canUpdate($record))
            ->schema([
                Select::make('school_class_id')
                    ->label('Nouvelle classe')
                    ->options(fn (Enrollment $record): array => SchoolClass::query()
                        ->where('school_year_id', $record->school_year_id)
                        ->whereKeyNot($record->school_class_id)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->required(),
            ])
            ->action(self::guarded(
                fn (Enrollment $record, array $data) => app(ManageEnrollment::class)->changeClass(
                    $record,
                    SchoolClass::query()->findOrFail($data['school_class_id']),
                    Filament::auth()->user(),
                ),
                'Classe modifiée.',
            ));
    }

    public static function departure(): Action
    {
        return Action::make('departure')
            ->label('Déclarer un départ')
            ->icon(Heroicon::OutlinedArrowRightStartOnRectangle)
            ->color('danger')
            ->visible(fn (Enrollment $record): bool => $record->status->isActive() && self::canUpdate($record))
            ->modalDescription('L\'élève libère sa place. Son dossier et son historique restent consultables.')
            ->schema([
                Radio::make('status')
                    ->label('Motif du départ')
                    ->options([
                        EnrollmentStatus::TransferredOut->value => 'Transfert vers une autre école',
                        EnrollmentStatus::Withdrawn->value => 'Départ (abandon, déménagement…)',
                    ])
                    ->default(EnrollmentStatus::TransferredOut->value)
                    ->live()
                    ->required(),
                DatePicker::make('left_on')
                    ->label('Date de départ')
                    ->default(now())
                    ->required(),
                TextInput::make('destination_school')
                    ->label('École d\'accueil')
                    ->visible(fn (Get $get): bool => $get('status') === EnrollmentStatus::TransferredOut->value)
                    ->maxLength(255),
                TextInput::make('leaving_reason')
                    ->label('Précisions')
                    ->maxLength(255),
            ])
            ->action(self::guarded(
                fn (Enrollment $record, array $data) => app(ManageEnrollment::class)->recordDeparture(
                    $record,
                    EnrollmentStatus::from($data['status']),
                    Carbon::parse($data['left_on']),
                    $data['leaving_reason'] ?? null,
                    $data['destination_school'] ?? null,
                    Filament::auth()->user(),
                ),
                'Départ enregistré.',
            ));
    }

    public static function documents(): Action
    {
        return Action::make('documents')
            ->label('Pièces du dossier')
            ->icon(Heroicon::OutlinedClipboardDocumentCheck)
            ->visible(fn (Enrollment $record): bool => self::canUpdate($record))
            ->fillForm(fn (Enrollment $record): array => ['documents' => $record->documents])
            ->schema([
                CheckboxList::make('documents')
                    ->label('Pièces fournies')
                    ->options(fn (): array => self::documentOptions()),
            ])
            ->action(function (Enrollment $record, array $data): void {
                $record->update(['documents' => array_values($data['documents'] ?? [])]);

                Notification::make()->success()->title('Dossier mis à jour.')->send();
            });
    }

    /**
     * Réinscription groupée : les élèves sélectionnés (d'une classe de
     * l'année précédente) rejoignent la classe choisie.
     */
    public static function reEnrollBulk(): BulkAction
    {
        return BulkAction::make('reEnroll')
            ->label('Réinscrire dans une classe…')
            ->icon(Heroicon::OutlinedArrowPathRoundedSquare)
            ->visible(fn (): bool => self::userCan('enrollment.manage'))
            ->modalDescription('Les élèves sélectionnés sont réinscrits dans la classe choisie. Ceux qui ne peuvent pas l\'être (déjà inscrits, exclus, classe complète) sont signalés.')
            ->schema([
                Select::make('school_class_id')
                    ->label('Classe de destination')
                    ->options(fn (): array => SchoolClass::query()
                        ->whereHas('schoolYear', fn ($query) => $query->where('status', '!=', 'closed'))
                        ->with('schoolYear')
                        ->get()
                        ->groupBy('schoolYear.name')
                        ->map(fn ($classes) => $classes->sortBy('name')->pluck('name', 'id')->all())
                        ->all())
                    ->required(),
            ])
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records, array $data): void {
                $schoolClass = SchoolClass::query()->with('schoolYear')->findOrFail($data['school_class_id']);
                $failures = [];
                $done = 0;

                /** @var Enrollment $enrollment */
                foreach ($records->load('student') as $enrollment) {
                    try {
                        app(ReEnrollStudent::class)->handle($enrollment->student, $schoolClass, actor: Filament::auth()->user());
                        $done++;
                    } catch (BusinessRuleViolation $exception) {
                        $failures[] = $exception->getMessage();
                    }
                }

                $notification = Notification::make()
                    ->title("{$done} élève(s) réinscrit(s) en {$schoolClass->name}.")
                    ->status($failures === [] ? 'success' : 'warning');

                if ($failures !== []) {
                    $notification->body(implode("\n", $failures))->persistent();
                }

                $notification->send();
            });
    }

    public static function studentCardsBulk(): BulkAction
    {
        return BulkAction::make('studentCards')
            ->label('Imprimer les cartes scolaires')
            ->icon(Heroicon::OutlinedIdentification)
            ->visible(fn (): bool => self::userCan('document.issue'))
            ->action(fn (Collection $records): ?StreamedResponse => self::download(
                fn (): string => app(IssueDocuments::class)->studentCards($records, Filament::auth()->user()),
                'cartes-scolaires.pdf',
            ));
    }

    /**
     * @return array<string, string>
     */
    public static function documentOptions(): array
    {
        $country = app(TenantContext::class)->get()?->country_code;

        return config("talenda.academic_presets.{$country}.enrollment_documents", []);
    }

    /**
     * Année ouverte, pour les filtres et valeurs par défaut.
     */
    public static function openYearId(): ?int
    {
        return SchoolYear::query()->open()->value('id');
    }

    /**
     * @param  Closure(): string  $render
     */
    private static function download(Closure $render, string $filename): ?StreamedResponse
    {
        try {
            $pdf = $render();
        } catch (BusinessRuleViolation $exception) {
            Notification::make()->danger()->title($exception->getMessage())->send();

            return null;
        }

        return response()->streamDownload(fn () => print ($pdf), $filename, ['Content-Type' => 'application/pdf']);
    }

    private static function guarded(Closure $callback, string $successMessage): Closure
    {
        return function (Enrollment $record, array $data = []) use ($callback, $successMessage): void {
            try {
                $callback($record, $data);
            } catch (BusinessRuleViolation $exception) {
                Notification::make()->danger()->title($exception->getMessage())->send();

                return;
            }

            Notification::make()->success()->title($successMessage)->send();
        };
    }

    private static function canUpdate(Enrollment $enrollment): bool
    {
        return Filament::auth()->user()?->can('update', $enrollment) ?? false;
    }

    private static function userCan(string $permission): bool
    {
        return Filament::auth()->user()?->can($permission) ?? false;
    }
}
