<?php

namespace Tests\Feature;

use App\Actions\Pm\RejectPmRecord;
use App\Enums\PmStatus;
use App\Enums\SignatureStage;
use App\Mail\PmReportMail;
use App\Models\Machine;
use App\Models\NotificationDispatch;
use App\Models\PmRecord;
use App\Models\User;
use App\Notifications\PmApprovalNotification;
use App\Notifications\PmScheduleDigestNotification;
use App\Services\Integration\IntegrationSettings;
use App\Services\Integration\PmReportBuilder;
use App\Services\Notifications\PmApprovalNotifier;
use Carbon\Carbon;
use Database\Seeders\RoleSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Settings → Notifications: matriks notifikasi In-App + Email.
 */
class NotificationModuleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $techMachine;
    private User $techUtility;
    private User $pic;
    private User $picOther;
    private User $atasan;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RoleSeeder::class);

        $this->admin = User::factory()->create(['name' => 'Admin'])->assignRole('admin');
        $this->techMachine = User::factory()->create(['name' => 'Tek Mesin'])->assignRole('tech-machines');
        $this->techUtility = User::factory()->create(['name' => 'Tek Utility'])->assignRole('tech-utilities');
        $this->pic = User::factory()->create(['name' => 'PIC A'])->assignRole('user');
        $this->picOther = User::factory()->create(['name' => 'PIC B'])->assignRole('user');
        $this->atasan = User::factory()->create(['name' => 'Atasan'])->assignRole('manager');
    }

    protected function tearDown(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    private function enableMail(array $extra = []): void
    {
        app(IntegrationSettings::class)->set([
            'mail.enabled' => true,
            'mail.driver' => 'graph',
            'mail.from_address' => 'pm-noreply@redbellgroup.com',
            'graph.tenant_id' => 'tenant-123',
            'graph.client_id' => 'client-456',
            'graph.client_secret' => 'rahasia-789',
            ...$extra,
        ]);
        app()->forgetScopedInstances();
    }

    private function settings(array $values): void
    {
        app(IntegrationSettings::class)->set($values);
        app()->forgetScopedInstances();
    }

    private function machine(string $code, string $type = 'mixer', int $week = 1, ?User $pic = null): Machine
    {
        $m = Machine::create(['code' => $code, 'name' => "Unit {$code}", 'location' => 'Factory Milenium', 'type' => $type, 'week_group' => $week, 'is_active' => true, 'sort_no' => 1]);
        if ($pic) {
            $m->forceFill(['pic_user_id' => $pic->id])->save();
        }

        return $m;
    }

    private function record(Machine $machine, PmStatus $status, ?User $tech = null, ?User $pic = null): PmRecord
    {
        return PmRecord::create([
            'machine_id' => $machine->id,
            'year' => 2026,
            'period' => 'sep-okt',
            'technician_id' => $tech?->id,
            'technician_name' => $tech?->name,
            'pic_user_id' => $pic?->id,
            'status' => $status,
        ]);
    }

    // ------------------------------------------------------------ 3. Approval

    public function test_alur_approval_mengirim_apps_dan_email_ke_penerima_yang_tepat(): void
    {
        $this->enableMail();
        Notification::fake();
        $this->travelTo(Carbon::create(2026, 9, 10, 9, 0));

        $record = $this->record($this->machine('MX-1'), PmStatus::Submitted, $this->techMachine, $this->pic);
        $notifier = app(PmApprovalNotifier::class);

        // 3.1 Teknisi selesai Create PM → User/PIC yang ditunjuk saja.
        $notifier->afterSign($record, SignatureStage::Technician);
        Notification::assertSentTo($this->pic, PmApprovalNotification::class, function (PmApprovalNotification $n, array $channels) use ($record) {
            $data = $n->toArray($this->pic);

            return $n->event === PmApprovalNotification::SUBMITTED
                && $channels === ['database', 'mail']
                && $data['doc_number'] === $record->documentNumber()
                && str_contains($data['unit'], 'Unit MX-1')
                && str_starts_with($data['url'], '/machines/');
        });
        Notification::assertNotSentTo([$this->picOther, $this->atasan, $this->admin], PmApprovalNotification::class);

        // 3.2 User/PIC Approve → Atasan (bukan Admin).
        $record->update(['status' => PmStatus::PicApproved]);
        $notifier->afterSign($record, SignatureStage::Pic);
        Notification::assertSentTo($this->atasan, PmApprovalNotification::class, fn ($n) => $n->event === PmApprovalNotification::PIC_APPROVED);
        Notification::assertNotSentTo($this->admin, PmApprovalNotification::class);

        // 3.3 Atasan Approve → Teknisi.
        $record->update(['status' => PmStatus::Approved]);
        $notifier->afterSign($record, SignatureStage::Supervisor);
        Notification::assertSentTo($this->techMachine, PmApprovalNotification::class, fn ($n) => $n->event === PmApprovalNotification::APPROVED);

        $this->assertSame(3, NotificationDispatch::where('trigger', 'event')->count());
    }

    public function test_revisi_hanya_apps_dengan_alasan_dan_peminta_revisi(): void
    {
        $this->enableMail();
        Notification::fake();

        $record = $this->record($this->machine('MX-2'), PmStatus::PicApproved, $this->techMachine, $this->pic);
        $this->actingAs($this->atasan);

        app(RejectPmRecord::class)->handle($record, 'Foto bearing kurang jelas');

        Notification::assertSentTo($this->techMachine, PmApprovalNotification::class, function (PmApprovalNotification $n, array $channels) {
            $data = $n->toArray($this->techMachine);

            // Matriks 3.4: Apps ✓, Email ✗.
            return $n->event === PmApprovalNotification::REVISION
                && $channels === ['database']
                && $data['reason'] === 'Foto bearing kurang jelas'
                && $data['actor'] === 'Atasan'
                && $data['actor_role'] === 'Atasan';
        });
    }

    public function test_apps_tetap_jalan_tanpa_email_dan_saklar_mematikan_notifikasi(): void
    {
        Notification::fake();
        $record = $this->record($this->machine('MX-3'), PmStatus::Submitted, $this->techMachine, $this->pic);

        app(PmApprovalNotifier::class)->afterSign($record, SignatureStage::Technician);
        Notification::assertSentTo($this->pic, PmApprovalNotification::class, fn ($n, $channels) => $channels === ['database']);

        $this->settings(['notif.submitted.app' => false, 'notif.submitted.email' => false]);
        Notification::fake();
        app(PmApprovalNotifier::class)->afterSign($record, SignatureStage::Technician);
        Notification::assertNothingSent();
    }

    // ------------------------------------------------------- 1. Reminder

    public function test_reminder_minggu_ke2_per_scope_dan_hanya_sekali_per_periode(): void
    {
        $this->enableMail();
        Notification::fake();

        $mx = $this->machine('MX-10', 'mixer', 1, $this->pic);
        $mx2 = $this->machine('MX-11', 'mixer', 3, $this->picOther);
        $ut = $this->machine('UT-10', 'utility', 4);
        $done = $this->machine('MX-12', 'mixer', 2, $this->pic);
        $this->record($done, PmStatus::Approved, $this->techMachine);

        // Minggu ke-1: belum dikirim.
        $this->travelTo(Carbon::create(2026, 9, 3, 7, 0));
        $this->artisan('notifications:pm-alert', ['type' => 'reminder'])->assertSuccessful();
        Notification::assertNothingSent();

        // Minggu ke-2 (8–14 Sep).
        $this->travelTo(Carbon::create(2026, 9, 8, 7, 0));
        $this->artisan('notifications:pm-alert', ['type' => 'reminder'])->assertSuccessful();

        $captured = [];
        foreach ([$this->techMachine, $this->techUtility, $this->pic, $this->picOther, $this->atasan] as $u) {
            Notification::assertSentTo($u, PmScheduleDigestNotification::class, function (PmScheduleDigestNotification $n) use ($u, &$captured) {
                $captured[$u->name] = collect($n->items)->pluck('code')->sort()->values()->all();

                return $n->kind === PmScheduleDigestNotification::REMINDER
                    && str_contains($n->title(), 'Sep–Okt 2026')
                    && str_contains($n->title(), 'Minggu ke-2');
            });
        }

        $this->assertSame(['MX-10', 'MX-11'], $captured['Tek Mesin']);
        $this->assertSame(['UT-10'], $captured['Tek Utility']);
        $this->assertSame(['MX-10'], $captured['PIC A']); // MX-12 sudah selesai
        $this->assertSame(['MX-11'], $captured['PIC B']);
        $this->assertSame(['MX-10', 'MX-11', 'UT-10'], $captured['Atasan']);
        Notification::assertNotSentTo($this->admin, PmScheduleDigestNotification::class);

        // Hari berikutnya masih Minggu ke-2: tidak dikirim lagi.
        Notification::fake();
        $this->travelTo(Carbon::create(2026, 9, 9, 7, 0));
        $this->artisan('notifications:pm-alert', ['type' => 'reminder'])->assertSuccessful();
        Notification::assertNothingSent();
    }

    public function test_pic_dari_riwayat_dipakai_bila_perangkat_belum_punya_pic(): void
    {
        Notification::fake();
        $mx = $this->machine('MX-20', 'mixer', 1);
        PmRecord::create(['machine_id' => $mx->id, 'year' => 2026, 'period' => 'jul-ags', 'pic_user_id' => $this->picOther->id, 'status' => PmStatus::Approved]);

        $this->travelTo(Carbon::create(2026, 9, 8, 7, 0));
        $this->artisan('notifications:pm-alert', ['type' => 'reminder'])->assertSuccessful();

        Notification::assertSentTo($this->picOther, PmScheduleDigestNotification::class);
        Notification::assertNotSentTo($this->pic, PmScheduleDigestNotification::class);
    }

    // ------------------------------------------------------- 2. Overdue

    public function test_keterlambatan_ke_teknisi_dan_atasan_lalu_berhenti_saat_pm_selesai(): void
    {
        $this->enableMail();
        Notification::fake();

        $late = $this->machine('MX-30', 'mixer', 1, $this->pic);
        $this->machine('MX-31', 'mixer', 6);          // minggu ke-6 belum lewat
        $lateUt = $this->machine('UT-30', 'utility', 1);

        $this->travelTo(Carbon::create(2026, 9, 10, 7, 0));
        $this->artisan('notifications:pm-alert', ['type' => 'overdue'])->assertSuccessful();

        Notification::assertSentTo($this->techMachine, PmScheduleDigestNotification::class, function (PmScheduleDigestNotification $n) {
            $item = $n->items[0];

            return $n->kind === PmScheduleDigestNotification::OVERDUE
                && count($n->items) === 1
                && $item['code'] === 'MX-30'
                && $item['location'] === 'Factory Milenium'
                && $item['week'] === 1
                && $item['days_late'] === 3; // batas 07/09, sekarang 10/09
        });
        Notification::assertSentTo($this->techUtility, PmScheduleDigestNotification::class, fn ($n) => collect($n->items)->pluck('code')->all() === ['UT-30']);
        Notification::assertSentTo($this->atasan, PmScheduleDigestNotification::class, fn ($n) => count($n->items) === 2);
        // Matriks 2: User/PIC tidak menerima keterlambatan.
        Notification::assertNotSentTo($this->pic, PmScheduleDigestNotification::class);

        // Jam yang sama tidak dikirim dua kali.
        Notification::fake();
        $this->artisan('notifications:pm-alert', ['type' => 'overdue'])->assertSuccessful();
        Notification::assertNothingSent();

        // Sore: MX-30 sudah dikerjakan (sedang approval) → hanya utility tersisa.
        $this->record($late, PmStatus::Submitted, $this->techMachine, $this->pic);
        $this->travelTo(Carbon::create(2026, 9, 10, 14, 0));
        $this->artisan('notifications:pm-alert', ['type' => 'overdue'])->assertSuccessful();
        Notification::assertNotSentTo($this->techMachine, PmScheduleDigestNotification::class);
        Notification::assertSentTo($this->atasan, PmScheduleDigestNotification::class, fn ($n) => collect($n->items)->pluck('code')->all() === ['UT-30']);

        // Semua beres → tidak ada kiriman.
        $this->record($lateUt, PmStatus::Approved, $this->techUtility);
        Notification::fake();
        $this->travelTo(Carbon::create(2026, 9, 11, 7, 0));
        $this->artisan('notifications:pm-alert', ['type' => 'overdue'])->assertSuccessful();
        Notification::assertNothingSent();
    }

    public function test_jadwal_keterlambatan_terdaftar_pukul_07_dan_14(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($e) => str_contains((string) $e->command, 'notifications:pm-alert overdue'));

        $this->assertEqualsCanonicalizing(['0 7 * * *', '0 14 * * *'], $events->pluck('expression')->all());
        $this->assertTrue($events->every(fn ($e) => $e->timezone === 'Asia/Jakarta'));
    }

    // ------------------------------------------------------- 4. Laporan

    public function test_laporan_ke_atasan_dan_tambahan_dengan_lampiran_pdf_dan_excel(): void
    {
        $this->enableMail(['report.recipients' => 'plant@redbellgroup.com']);
        Mail::fake();
        $this->travelTo(Carbon::create(2026, 9, 14, 7, 0));

        $late = $this->machine('MX-40', 'mixer', 1);
        $this->record($this->machine('MX-41', 'mixer', 1), PmStatus::Approved, $this->techMachine);

        $this->artisan('integration:send-pm-report', ['type' => 'weekly'])->assertSuccessful();

        Mail::assertSent(PmReportMail::class, function (PmReportMail $mail) {
            return $mail->hasTo($this->atasan->email)
                && $mail->hasTo('plant@redbellgroup.com')
                && ! $mail->hasTo($this->admin->email)
                && $mail->report['summary']['total'] === 2
                && $mail->report['summary']['done'] === 1
                && $mail->report['summary']['overdue'] === 1
                && (float) $mail->report['summary']['percent'] === 50.0
                && $mail->report['overdueList'][0]['code'] === 'MX-40'
                && count($mail->attachments()) === 2;
        });

        $this->assertSame('Laporan PM Bulanan', PmReportBuilder::build(PmReportBuilder::MONTHLY)['title']);
    }

    public function test_pdf_laporan_bisa_dirender(): void
    {
        $this->machine('MX-50', 'mixer', 1);
        $this->travelTo(Carbon::create(2026, 9, 14, 7, 0));

        $mail = new PmReportMail(PmReportBuilder::build(PmReportBuilder::WEEKLY), true, false);
        $html = view('reports.notification-report', ['report' => $mail->report])->render();

        $this->assertStringContainsString('MX-50', $html);
        $this->assertStringContainsString('Pencapaian', $html);
        $mail->assertSeeInHtml('Perangkat terlambat');
    }

    // ------------------------------------------------------- Halaman

    public function test_kotak_masuk_untuk_semua_user_pengaturan_hanya_admin(): void
    {
        $this->actingAs($this->techMachine)->get(route('notifications.index', ['tab' => 'matrix']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Settings/Notifications')
                ->where('tab', 'inbox')
                ->where('canManage', false)
                ->where('admin', null));

        $this->actingAs($this->techMachine)->put(route('notifications.update'), [])->assertForbidden();
        $this->actingAs($this->techMachine)->put(route('notifications.pic'), [])->assertForbidden();
        $this->actingAs($this->techMachine)->post(route('notifications.send'), ['type' => 'overdue'])->assertForbidden();

        $this->actingAs($this->admin)->get(route('notifications.index', ['tab' => 'pic']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('tab', 'pic')
                ->where('canManage', true)
                ->has('admin.matrix')
                ->has('admin.devices')
                ->where('admin.matrix.overdue_times', '07:00,14:00')
                ->where('admin.matrix.revision_email', false));
    }

    public function test_admin_menyimpan_matriks_dan_menetapkan_pic(): void
    {
        $payload = $this->actingAs($this->admin)->get(route('notifications.index'))->viewData('page')['props']['admin']['matrix'];
        $payload['overdue_times'] = '14:00, 07:00, 16:30';
        $payload['reminder_email'] = false;

        $this->actingAs($this->admin)->put(route('notifications.update'), $payload)->assertSessionHasNoErrors();
        app()->forgetScopedInstances();
        $this->assertSame('07:00,14:00,16:30', app(IntegrationSettings::class)->get('notif.overdue.times'));
        $this->assertFalse(app(IntegrationSettings::class)->bool('notif.reminder.email'));

        $this->actingAs($this->admin)->put(route('notifications.update'), ['overdue_times' => '7 pagi'] + $payload)
            ->assertSessionHasErrors('overdue_times');

        $a = $this->machine('MX-60');
        $b = $this->machine('MX-61');
        $this->actingAs($this->admin)->put(route('notifications.pic'), ['machine_ids' => [$a->id, $b->id], 'pic_user_id' => $this->pic->id])
            ->assertSessionHasNoErrors();
        $this->assertSame($this->pic->id, $a->fresh()->pic_user_id);
        $this->assertSame($this->pic->id, $b->fresh()->pic_user_id);

        // Hanya User PIC yang valid.
        $this->actingAs($this->admin)->put(route('notifications.pic'), ['machine_ids' => [$a->id], 'pic_user_id' => $this->techMachine->id])
            ->assertSessionHasErrors('pic_user_id');
    }

    public function test_membuka_notifikasi_menandai_dibaca_dan_tidak_bisa_membuka_milik_orang_lain(): void
    {
        $record = $this->record($this->machine('MX-70'), PmStatus::Submitted, $this->techMachine, $this->pic);
        app(PmApprovalNotifier::class)->afterSign($record, SignatureStage::Technician);

        $n = $this->pic->notifications()->sole();
        $this->assertNull($n->read_at);

        $this->actingAs($this->picOther)->get(route('notifications.open', $n->id))->assertNotFound();

        $this->actingAs($this->pic)->getJson(route('notifications.feed'))
            ->assertOk()->assertJsonPath('unread', 1)->assertJsonPath('items.0.category', 'approval');

        $this->actingAs($this->pic)->get(route('notifications.open', $n->id))
            ->assertRedirect($n->data['url']);
        $this->assertNotNull($n->fresh()->read_at);
    }

    public function test_pratinjau_tidak_mengirim(): void
    {
        Notification::fake();
        $this->machine('MX-80', 'mixer', 1);
        $this->travelTo(Carbon::create(2026, 9, 10, 7, 0));

        $this->actingAs($this->admin)->postJson(route('notifications.preview'), ['type' => 'overdue'])
            ->assertOk()
            ->assertJsonPath('status', 'preview')
            ->assertJsonPath('items', 1);

        Notification::assertNothingSent();
        $this->assertSame(0, NotificationDispatch::count());
    }
}
