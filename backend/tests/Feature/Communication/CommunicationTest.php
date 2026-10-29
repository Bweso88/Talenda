<?php

use App\Models\User;
use App\Modules\Academic\Enums\TermStatus;
use App\Modules\Academic\Models\Level;
use App\Modules\Academic\Models\SchoolClass;
use App\Modules\Academic\Models\SchoolYear;
use App\Modules\Assessments\Actions\ManageTerm;
use App\Modules\Communication\Actions\PublishAnnouncement;
use App\Modules\Communication\Actions\SendParentCredentials;
use App\Modules\Communication\Actions\SendPaymentReminders;
use App\Modules\Communication\Enums\AnnouncementAudience;
use App\Modules\Communication\Enums\NotificationType;
use App\Modules\Communication\Enums\SmsPurpose;
use App\Modules\Communication\Enums\SmsStatus;
use App\Modules\Communication\Models\CommunicationSetting;
use App\Modules\Communication\Models\DeviceToken;
use App\Modules\Communication\Models\SmsMessage;
use App\Modules\Communication\Models\UserNotification;
use App\Modules\Communication\Sms\SmsDeliveryFailed;
use App\Modules\Communication\Sms\SmsGateway;
use App\Modules\Communication\Sms\SmsText;
use App\Modules\Core\Exceptions\BusinessRuleViolation;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Enrollments\Actions\EnrollNewStudent;
use App\Modules\Enrollments\Models\Enrollment;
use App\Modules\Enrollments\Models\Guardian;
use App\Modules\Finances\Actions\RecordPayment;
use App\Modules\Finances\Enums\PaymentMethod;
use App\Modules\Finances\Models\Invoice;
use App\Modules\Finances\Support\UnpaidReport;
use App\Modules\ParentPortal\Actions\GrantParentAccess;
use App\Modules\SchoolLife\Actions\JustifyAbsences;
use App\Modules\SchoolLife\Actions\TakeAttendance;
use App\Modules\SchoolLife\Enums\JustificationReason;
use App\Modules\SchoolLife\Models\AttendanceSlot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Laravel\Sanctum\Sanctum;

/**
 * Fournisseur SMS de test : garde les messages réellement remis.
 */
class FakeSmsGateway implements SmsGateway
{
    /** @var list<array{phone: string, body: string, sender: string}> */
    public array $sent = [];

    public bool $fail = false;

    public function name(): string
    {
        return 'fake';
    }

    public function send(string $phone, string $body, string $sender): string
    {
        if ($this->fail) {
            throw new SmsDeliveryFailed('Crédit SMS épuisé');
        }

        $this->sent[] = compact('phone', 'body', 'sender');

        return 'fake-'.count($this->sent);
    }
}

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-11-16 09:00', 'UTC'));
    $this->sms = new FakeSmsGateway;
    app()->instance(SmsGateway::class, $this->sms);

    [$this->school, $this->admin] = provisionSchool('lycee-a');
    app(TenantContext::class)->set($this->school);
    feeGrid('6E');

    $year = SchoolYear::query()->open()->sole();
    $this->term = $year->terms()->orderBy('position')->first();
    $this->class = SchoolClass::create(['school_year_id' => $year->id, 'level_id' => Level::firstWhere('code', '6E')->id, 'name' => '6e A']);

    $enroll = fn (string $firstNames, string $phone, string $gender = 'F'): Enrollment => app(EnrollNewStudent::class)->handle(
        newStudentData(['first_names' => $firstNames, 'gender' => $gender, 'birth_date' => $firstNames === 'Arsène' ? '2013-01-20' : '2014-05-12']),
        [['relationship' => 'mother', 'last_name' => 'Mabiala', 'first_names' => 'Prisca', 'phone' => $phone, 'is_primary_contact' => true, 'is_financially_responsible' => true]],
        $this->class,
        allowPossibleDuplicate: true,
    );
    $this->grace = $enroll('Grâce', '+242063333333');
    $this->arsene = $enroll('Arsène', '+242063333333', 'M');
    $this->other = $enroll('Exaucée', '+242065550009');
    Enrollment::query()->update(['enrolled_on' => '2026-10-01']);

    app(GrantParentAccess::class)->handle(Guardian::firstWhere('phone', '+242063333333'));
    $this->parent = User::firstWhere('phone', '+242063333333');
});

function notificationsOf(User $user): Collection
{
    return UserNotification::query()->where('user_id', $user->id)->latest('id')->get();
}

it('compte les SMS facturés selon l\'alphabet utilisé', function () {
    expect(SmsText::segments(str_repeat('é', 160)))->toBe(1)
        ->and(SmsText::segments(str_repeat('a', 161)))->toBe(2)
        ->and(SmsText::segments('Fête'))->toBe(1)
        ->and(SmsText::isGsm('Fête'))->toBeFalse()
        ->and(SmsText::segments(str_repeat('ê', 71)))->toBe(2)
        ->and(SmsText::clean("50\u{202F}000\u{00A0}FCFA «\u{00A0}test\u{00A0}»"))->toBe('50 000 FCFA "test"')
        // Un « â » doublerait le coût : remplacé ; é, è, à restent.
        ->and(SmsText::clean('Grâce, fête à l\'école, élève, leçon'))->toBe('Grace, fete à l\'école, élève, lecon')
        ->and(SmsText::isGsm(SmsText::clean('Grâce Ngôma')))->toBeTrue();
});

it('prévient la famille d\'un paiement dans l\'app, et par SMS si l\'école l\'a activé', function () {
    $invoice = Invoice::firstWhere('enrollment_id', $this->grace->id);
    app(RecordPayment::class)->handle($invoice, 50000, PaymentMethod::MobileMoney, $this->admin, 'MP1');

    expect(notificationsOf($this->parent)->first())
        ->type->toBe(NotificationType::Payment)
        ->body->toContain('50 000 FCFA pour Grâce')
        ->and($this->sms->sent)->toBe([]);

    CommunicationSetting::current()->update(['sms_on_payment' => true, 'sms_sender' => 'LYCEE A']);
    app(RecordPayment::class)->handle($invoice, 10000, PaymentMethod::MobileMoney, $this->admin, 'MP2');

    expect($this->sms->sent)->toHaveCount(1)
        ->and($this->sms->sent[0]['phone'])->toBe('+242063333333')
        ->and($this->sms->sent[0]['sender'])->toBe('LYCEE A')
        ->and($this->sms->sent[0]['body'])->toContain('paiement de 10 000 FCFA enregistré pour Grace')
        ->and(SmsMessage::sole())->status->toBe(SmsStatus::Sent)->segments->toBe(1)->purpose->toBe(SmsPurpose::Payment);
});

it('prévient d\'une absence une seule fois, même si l\'appel est corrigé', function () {
    CommunicationSetting::current()->update(['sms_on_absence' => true]);
    $morning = AttendanceSlot::firstWhere('name', 'Matin');
    $take = fn (array $entries) => app(TakeAttendance::class)->handle($this->class, Carbon::parse('2026-11-16'), $morning, $entries, $this->admin);

    $take([$this->arsene->id => ['status' => 'absent']]);
    $take([$this->arsene->id => ['status' => 'absent', 'note' => 'malade ?']]);

    $notification = notificationsOf($this->parent)->sole();

    expect($notification->title)->toBe('Arsène absent')
        ->and($notification->body)->toContain('Matin du lundi 16 novembre')
        ->and($notification->data)->toMatchArray(['child' => $this->arsene->student->uuid, 'tab' => 'attendance'])
        ->and($this->sms->sent)->toHaveCount(1)
        ->and($this->sms->sent[0]['body'])->toContain('Arsène MABIALA (6e A) est absent le 16/11, Matin');
});

it('prévient chaque famille de la publication des bulletins', function () {
    $this->term->update(['status' => TermStatus::Locked]);
    app(ManageTerm::class)->publish($this->term->refresh(), $this->admin);

    $titles = notificationsOf($this->parent)->pluck('title')->all();

    expect($titles)->toBe(['Bulletin du Premier trimestre', 'Bulletin du Premier trimestre'])
        ->and(notificationsOf($this->parent)->pluck('type')->unique()->all())->toBe([NotificationType::ReportCard]);
});

it('prévient le parent de l\'examen de son justificatif', function () {
    $justification = app(JustifyAbsences::class)->submitByGuardian($this->grace, Carbon::parse('2026-11-13'), Carbon::parse('2026-11-13'), JustificationReason::Illness, null, $this->parent);
    app(JustifyAbsences::class)->review($justification, false, $this->admin);

    expect(notificationsOf($this->parent)->first())
        ->title->toBe('Justificatif refusé')
        ->body->toContain('Absence du 13/11/2026 de Grâce');
});

it('publie une annonce : app pour les parents qui l\'ont, un SMS par famille', function () {
    $announcement = app(PublishAnnouncement::class)->handle('Réunion des parents', 'Samedi 21 novembre à 9 h, au préau.', AnnouncementAudience::SchoolClass, $this->class->id, true, $this->admin);

    expect($announcement->audience_label)->toBe('Classe 6e A')
        ->and($announcement->families_count)->toBe(2)
        ->and($announcement->app_recipients_count)->toBe(1)
        ->and($announcement->sms_count)->toBe(2)
        ->and(collect($this->sms->sent)->pluck('phone')->all())->toBe(['+242063333333', '+242065550009'])
        ->and(notificationsOf($this->parent)->sole()->title)->toBe('Réunion des parents');

    expect(fn () => app(PublishAnnouncement::class)->handle('Vide', 'x', AnnouncementAudience::Level, Level::firstWhere('code', 'TLE')->id, false, $this->admin))
        ->toThrow(BusinessRuleViolation::class, 'Aucun élève');
});

it('relance les impayés par SMS, une seule fois par jour', function () {
    $rows = app(UnpaidReport::class)->rows(SchoolYear::query()->open()->sole());

    expect(app(SendPaymentReminders::class)->handle($rows, $this->admin))->toBe(['sent' => 3, 'skipped' => 0])
        ->and($this->sms->sent[0]['body'])->toContain('présente un retard de')
        ->and(collect($this->sms->sent)->pluck('body')->implode(' '))->toContain("la scolarité d'Exaucée MABIALA (6e A)")
        ->and(notificationsOf($this->parent)->pluck('type')->unique()->all())->toBe([NotificationType::PaymentReminder]);

    expect(app(SendPaymentReminders::class)->handle($rows, $this->admin))->toBe(['sent' => 0, 'skipped' => 3]);
});

it('envoie le mot de passe provisoire sans le garder dans le journal', function () {
    app(SendParentCredentials::class)->handle(Guardian::firstWhere('phone', '+242065550009'), 'Abc123xyz9', $this->admin);

    expect($this->sms->sent[0]['body'])->toContain('Mot de passe provisoire : Abc123xyz9')
        ->and(SmsMessage::sole()->body)->not->toContain('Abc123xyz9')->toContain('••••••');
});

it('note l\'échec d\'envoi d\'un SMS dans le journal', function () {
    $this->sms->fail = true;
    app(SendParentCredentials::class)->handle(Guardian::firstWhere('phone', '+242065550009'), null);

    expect(SmsMessage::sole())->status->toBe(SmsStatus::Failed)->error->toBe('Crédit SMS épuisé');
});

it('liste les notifications du parent dans l\'app et les marque lues', function () {
    app(PublishAnnouncement::class)->handle('Fête de l\'école', 'Le 12 décembre.', AnnouncementAudience::School, null, false, $this->admin);
    $invoice = Invoice::firstWhere('enrollment_id', $this->grace->id);
    app(RecordPayment::class)->handle($invoice, 50000, PaymentMethod::MobileMoney, $this->admin, 'MP1');

    Sanctum::actingAs($this->parent);
    $this->withHeader('X-Tenant', $this->school->uuid);

    $uuid = $this->getJson('/api/v1/notifications')
        ->assertOk()
        ->assertJsonPath('unread_count', 2)
        ->assertJsonPath('data.0.type', 'payment')
        ->assertJsonPath('data.1.title', 'Fête de l\'école')
        ->json('data.0.uuid');

    $this->postJson("/api/v1/notifications/{$uuid}/read")->assertNoContent();
    $this->getJson('/api/v1/notifications')->assertJsonPath('unread_count', 1)->assertJsonPath('data.0.read', true);

    $this->postJson('/api/v1/notifications/read-all')->assertNoContent();
    $this->getJson('/api/v1/notifications')->assertJsonPath('unread_count', 0);
});

it('enregistre et retire le jeton push d\'un appareil', function () {
    Sanctum::actingAs($this->parent);

    $this->postJson('/api/v1/device-tokens', ['token' => 'fcm-123', 'platform' => 'android'])->assertNoContent();
    expect(DeviceToken::sole()->user_id)->toBe($this->parent->id);

    $this->deleteJson('/api/v1/device-tokens', ['token' => 'fcm-123'])->assertNoContent();
    expect(DeviceToken::count())->toBe(0);
});
