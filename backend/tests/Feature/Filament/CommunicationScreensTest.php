<?php

use App\Filament\School\Pages\CommunicationSettingsPage;
use App\Filament\School\Pages\UnpaidReport;
use App\Filament\School\Resources\Announcements\Pages\ManageAnnouncements;
use App\Filament\School\Resources\SmsMessages\Pages\ListSmsMessages;
use App\Models\User;
use App\Modules\Academic\Models\Level;
use App\Modules\Academic\Models\SchoolClass;
use App\Modules\Academic\Models\SchoolYear;
use App\Modules\Communication\Enums\SmsPurpose;
use App\Modules\Communication\Enums\SmsStatus;
use App\Modules\Communication\Models\Announcement;
use App\Modules\Communication\Models\CommunicationSetting;
use App\Modules\Communication\Models\SmsMessage;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Enrollments\Actions\EnrollNewStudent;
use App\Modules\Enrollments\Models\Enrollment;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-11-16 09:00', 'UTC'));
    [$this->school, $this->admin] = provisionSchool('lycee-a');
    app(TenantContext::class)->set($this->school);
    feeGrid('6E');

    $year = SchoolYear::query()->open()->sole();
    $this->class = SchoolClass::create(['school_year_id' => $year->id, 'level_id' => Level::firstWhere('code', '6E')->id, 'name' => '6e A']);
    $this->enrollment = app(EnrollNewStudent::class)->handle(
        newStudentData(),
        [['relationship' => 'mother', 'last_name' => 'Mabiala', 'first_names' => 'Prisca', 'phone' => '+242063333333', 'is_financially_responsible' => true]],
        $this->class,
    );
    Enrollment::query()->update(['enrolled_on' => '2026-10-01']);
    actInSchool($this->admin, $this->school);
});

it('ouvre les écrans de communication pour la direction', function (string $path) {
    $this->get("/ecole/lycee-a/{$path}")->assertOk();
})->with(['annonces', 'journal-sms', 'reglages-communication']);

it('laisse le secrétariat publier des annonces, sans voir le journal SMS ni les réglages', function () {
    $secretary = User::factory()->memberOf($this->school, 'secretary')->create();

    $this->actingAs($secretary)->get('/ecole/lycee-a/annonces')->assertOk();
    $this->actingAs($secretary)->get('/ecole/lycee-a/journal-sms')->assertForbidden();
    $this->actingAs($secretary)->get('/ecole/lycee-a/reglages-communication')->assertForbidden();
});

it('publie une annonce à une classe avec envoi par SMS', function () {
    Livewire::test(ManageAnnouncements::class)
        ->callAction('create', [
            'title' => 'Réunion des parents',
            'body' => 'Samedi à 9 h.',
            'audience' => 'class',
            'audience_id' => $this->class->id,
            'send_sms' => true,
        ])
        ->assertHasNoActionErrors()
        ->assertNotified('Annonce publiée');

    expect(Announcement::sole())->audience_label->toBe('Classe 6e A')->sms_count->toBe(1)
        ->and(SmsMessage::sole()->purpose)->toBe(SmsPurpose::Announcement);
});

it('enregistre les réglages de communication', function () {
    Livewire::test(CommunicationSettingsPage::class)
        ->fillForm(['sms_sender' => 'lycee a', 'sms_on_absence' => true])
        ->callAction('save')
        ->assertNotified('Réglages de communication enregistrés.');

    expect(CommunicationSetting::current())->sms_sender->toBe('LYCEE A')->sms_on_absence->toBeTrue()->sms_on_payment->toBeFalse();
});

it('relance par SMS toutes les familles en retard depuis l\'état des impayés', function () {
    Livewire::test(UnpaidReport::class)
        ->callAction('remindAll')
        ->assertNotified('1 famille(s) relancée(s) par SMS');

    expect(SmsMessage::sole())->purpose->toBe(SmsPurpose::PaymentReminder)->phone->toBe('+242063333333');
});

it('renvoie un SMS en échec depuis le journal', function () {
    $sms = SmsMessage::create(['phone' => '+242063333333', 'body' => 'Test', 'segments' => 1, 'purpose' => SmsPurpose::Announcement, 'status' => SmsStatus::Failed, 'provider' => 'log', 'error' => 'Réseau']);

    Livewire::test(ListSmsMessages::class)
        ->assertCanSeeTableRecords([$sms])
        ->callTableAction('retry', $sms)
        ->assertNotified('SMS renvoyé.');

    expect($sms->refresh()->status)->toBe(SmsStatus::Sent);
});
