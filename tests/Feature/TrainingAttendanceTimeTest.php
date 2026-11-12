<?php

namespace Tests\Feature;

use App\Models\Training;
use App\Models\TrainingParticipant;
use App\Models\User;
use Tests\TestCase;

/**
 * Batas waktu presensi: seperti pretest, posttest, dan form evaluasi, presensi bisa dibuka sekarang atau dijadwalkan,
 * dan tertutup sendiri pada jam tutupnya.
 */
class TrainingAttendanceTimeTest extends TestCase
{
    private const PORTAL = '/training/portal/token_presensi';

    private User $admin;

    private Training $training;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setTime(9, 18, 0));

        $this->admin = User::where('username', 'admin')->sole();
        $this->training = Training::create([
            'title' => 'Pelatihan Presensi Berbatas Waktu', 'trainer_id' => $this->admin->id, 'training_date' => now()->toDateString(),
            'start_time' => '09:00', 'end_time' => '10:00', 'zoom_link' => 'https://zoom.us/test', 'setup_completed_at' => now(),
        ]);
        TrainingParticipant::create([
            'training_id' => $this->training->id, 'user_id' => User::factory()->staff()->create()->id,
            'token' => 'token_presensi', 'attendance_status' => 'pending',
        ]);
    }

    /**
     * @param  array<string, string>  $choice
     */
    private function toggle(array $choice = [])
    {
        return $this->actingAs($this->admin)->post(route('training.toggle-attendance', $this->training), $choice);
    }

    private function attendanceStatus(): string
    {
        return TrainingParticipant::where('token', 'token_presensi')->sole()->attendance_status;
    }

    public function test_attendance_opens_now_without_a_time_limit_by_default(): void
    {
        $this->toggle(['open' => '1'])->assertSessionHas('success', 'Presensi kehadiran pelatihan telah DIBUKA untuk seluruh peserta!');

        $training = $this->training->fresh();
        $this->assertTrue($training->isAttendanceOpen());
        $this->assertNull($training->attendance_closes_at);
        $this->get(self::PORTAL.'/status')->assertJson(['is_attendance_active' => true, 'attendance_seconds_left' => null, 'attendance_timed_out' => false]);
        $this->actingAs($this->admin)->get(route('training.show', $training))->assertOk()
            ->assertSee('Presensi aktif')->assertSee('tanpa batas waktu')->assertSee('Kunci Presensi')->assertSee('Atur waktu Presensi');

        // Tetap terbuka sampai dikunci.
        $this->travel(3)->hours();
        $this->assertTrue($training->fresh()->isAttendanceOpen());
        $this->toggle(['open' => '0'])->assertSessionHas('success', 'Presensi kehadiran pelatihan telah DITUTUP sementara.');
        $this->assertFalse($training->fresh()->isAttendanceOpen());
    }

    public function test_attendance_closes_by_itself_at_its_closing_time(): void
    {
        $this->toggle(['open' => '1', 'close_mode' => 'minutes', 'close_minutes' => '10'])
            ->assertSessionHas('success', 'Presensi kehadiran pelatihan telah DIBUKA untuk seluruh peserta, tutup otomatis pukul 09:28.');

        // Terbuka: peserta melihat form dan batas waktunya.
        $this->get(self::PORTAL.'/status')->assertJson(['is_attendance_active' => true, 'attendance_seconds_left' => 600, 'attendance_closes_at' => '09:28']);
        $this->get(self::PORTAL)->assertOk()->assertSee('id="attendanceBody" data-state="open"', false)->assertSee('data-deadline="attendance" data-seconds-left="600"', false);
        $this->actingAs($this->admin)->get(route('training.show', $this->training))->assertOk()
            ->assertSee('tutup otomatis 09:28')->assertSee('+5 menit')->assertSee('data-phase-timer="600"', false);

        // 09.28: tertutup sendiri, tanpa dikunci pemateri.
        $this->travelTo(now()->setTime(9, 28, 0));
        $training = $this->training->fresh();
        $this->assertFalse($training->isAttendanceOpen());
        $this->assertTrue($training->phaseTimedOut('attendance'));
        $this->get(self::PORTAL.'/status')->assertJson(['is_attendance_active' => false, 'attendance_seconds_left' => null, 'attendance_timed_out' => true]);
        $this->get(self::PORTAL)->assertOk()->assertSee('data-attendance-locked-title>Presensi Kehadiran Sudah Ditutup</h6>', false)->assertSee('id="attendanceBody" data-state="locked"', false);
        $this->actingAs($this->admin)->get(route('training.show', $training))->assertOk()->assertSee('Waktu habis, presensi ditutup otomatis');
    }

    public function test_attendance_sent_just_after_the_closing_time_is_still_accepted(): void
    {
        $this->toggle(['open' => '1', 'close_mode' => 'clock', 'close_time' => '09:30'])->assertSessionHas('success');

        // Foto yang sedang diunggah saat waktu habis tidak ditolak...
        $this->travelTo(now()->setTime(9, 31, 0));
        $this->post(self::PORTAL.'/attendance', ['status' => 'hadir'])->assertSessionHas('success');
        $this->assertSame('hadir', $this->attendanceStatus());

        // ...tapi setelah masa toleransi lewat, presensi benar-benar tertutup.
        TrainingParticipant::where('token', 'token_presensi')->update(['attendance_status' => 'pending']);
        $this->travelTo(now()->setTime(9, 30, 0)->addMinutes(Training::ATTENDANCE_GRACE_MINUTES));
        $this->post(self::PORTAL.'/attendance', ['status' => 'hadir'])->assertSessionHas('warning', 'Waktu pengisian presensi sudah habis.');
        $this->assertSame('pending', $this->attendanceStatus());
    }

    public function test_locked_attendance_is_closed_right_away(): void
    {
        $this->toggle(['open' => '1', 'close_mode' => 'minutes', 'close_minutes' => '10'])->assertSessionHas('success');
        $this->toggle(['open' => '0'])->assertSessionHas('success');

        // Dikunci manual: tidak ada masa toleransi, dan jam tutupnya ikut terhapus.
        $training = $this->training->fresh();
        $this->assertFalse($training->is_attendance_active);
        $this->assertNull($training->attendance_closes_at);
        $this->post(self::PORTAL.'/attendance', ['status' => 'hadir'])->assertSessionHas('warning');
        $this->assertSame('pending', $this->attendanceStatus());
    }

    public function test_attendance_can_be_scheduled_to_open_and_close_by_itself(): void
    {
        // Pukul 09.18 pemateri menjadwalkan: buka 09.40, tutup 09.50.
        $this->toggle(['open' => '1', 'open_mode' => 'clock', 'open_time' => '09:40', 'close_mode' => 'clock', 'close_time' => '09:50'])
            ->assertSessionHas('success', 'Presensi kehadiran pelatihan DIJADWALKAN buka pukul 09:40 dan tutup otomatis pukul 09:50.');

        $training = $this->training->fresh();
        $this->assertSame('09:40:00', $training->attendance_opens_at->format('H:i:s'));
        $this->assertSame('09:50:00', $training->attendance_closes_at->format('H:i:s'));
        $this->assertFalse($training->isAttendanceOpen());
        $this->assertTrue($training->isAttendanceOpenOrScheduled());

        // Belum waktunya: peserta melihat jam bukanya dan belum bisa absen.
        $this->get(self::PORTAL.'/status')->assertJson(['is_attendance_active' => false, 'attendance_opens_at' => 'pukul 09:40', 'attendance_timed_out' => false]);
        $this->get(self::PORTAL)->assertOk()->assertSee('Presensi dibuka otomatis pukul 09:40 WIB.')->assertSee('id="attendanceBody" data-state="locked"', false);
        $this->post(self::PORTAL.'/attendance', ['status' => 'hadir'])->assertSessionHas('warning', 'Presensi dibuka otomatis pukul 09:40 WIB.');
        $this->assertSame('pending', $this->attendanceStatus());
        $this->actingAs($this->admin)->get(route('training.show', $training))->assertOk()
            ->assertSee('Terjadwal buka pukul 09:40')->assertSee('tutup 09:50')->assertSee('Ubah jadwal')->assertSee('Batalkan jadwal');

        // 09.40: terbuka sendiri.
        $this->travelTo(now()->setTime(9, 40, 0));
        $this->get(self::PORTAL.'/status')->assertJson(['is_attendance_active' => true, 'attendance_opens_at' => null, 'attendance_seconds_left' => 600]);
        $this->assertSame('Isi presensi', TrainingParticipant::with(['training.evaluationQuestions', 'quizResult', 'pretestResult', 'evaluation'])->where('token', 'token_presensi')->sole()->nextStep()['label']);
        $this->post(self::PORTAL.'/attendance', ['status' => 'hadir'])->assertSessionHas('success');
        $this->assertSame('hadir', $this->attendanceStatus());

        // "Batalkan jadwal" / kunci menghapus jadwalnya.
        $this->toggle(['open' => '0'])->assertSessionHas('success');
        $this->assertNull($this->training->fresh()->attendance_opens_at);
    }

    public function test_closing_time_must_be_ahead_and_after_the_opening(): void
    {
        $this->toggle(['open' => '1', 'close_mode' => 'clock', 'close_time' => '09:10'])->assertSessionHasErrors('close_time');
        $this->toggle(['open' => '1', 'open_mode' => 'clock', 'open_time' => '09:40', 'close_mode' => 'clock', 'close_time' => '09:30'])
            ->assertSessionHasErrors(['close_time' => 'Jam tutup 09:30 harus setelah jam buka 09:40.']);
        $this->toggle(['open' => '1', 'close_mode' => 'minutes'])->assertSessionHasErrors(['close_minutes' => 'Isi berapa menit terbukanya.']);

        $this->assertFalse($this->training->fresh()->is_attendance_active);
    }

    public function test_trainer_can_change_or_extend_the_time_while_attendance_is_open(): void
    {
        // Hanya bisa diatur selama terbuka.
        $this->actingAs($this->admin)->post(route('training.quiz-deadline', $this->training), ['phase' => 'attendance', 'close_mode' => 'minutes', 'close_minutes' => '5'])
            ->assertSessionHas('error', 'Waktu hanya bisa diatur selama Presensi terbuka. Buka Presensi terlebih dahulu.');
        $this->actingAs($this->admin)->post(route('training.extend-quiz-time', $this->training), ['phase' => 'attendance', 'minutes' => '5'])->assertSessionHas('error');

        $this->toggle(['open' => '1'])->assertSessionHas('success');
        $this->actingAs($this->admin)->post(route('training.quiz-deadline', $this->training), ['phase' => 'attendance', 'close_mode' => 'clock', 'close_time' => '09:30'])
            ->assertSessionHas('success', 'Presensi tutup otomatis pukul 09:30.');
        $this->actingAs($this->admin)->post(route('training.extend-quiz-time', $this->training), ['phase' => 'attendance', 'minutes' => '5'])
            ->assertSessionHas('success', 'Presensi ditambah 5 menit, tutup otomatis pukul 09:35.');
        $this->assertSame('09:35:00', $this->training->fresh()->attendance_closes_at->format('H:i:s'));

        $this->actingAs($this->admin)->post(route('training.quiz-deadline', $this->training), ['phase' => 'attendance', 'close_mode' => 'none'])
            ->assertSessionHas('success', 'Presensi sekarang tanpa batas waktu; kunci secara manual bila sudah selesai.');
        $this->assertNull($this->training->fresh()->attendance_closes_at);

        // Peserta biasa tidak boleh mengaturnya.
        $this->actingAs(User::factory()->staff()->create())->from('/my-trainings')
            ->post(route('training.quiz-deadline', $this->training), ['phase' => 'attendance', 'close_mode' => 'minutes', 'close_minutes' => '5'])->assertSessionHas('error');
        $this->assertNull($this->training->fresh()->attendance_closes_at);
    }

    public function test_lock_pressed_after_time_ran_out_does_not_reopen_and_reopening_starts_fresh(): void
    {
        $this->toggle(['open' => '1', 'close_mode' => 'minutes', 'close_minutes' => '5'])->assertSessionHas('success');
        $this->travel(6)->minutes();

        // Tombol "Kunci" yang ditekan tepat setelah waktu habis mengunci, bukan membuka lagi.
        $this->toggle(['open' => '0'])->assertSessionHas('success', 'Presensi kehadiran pelatihan telah DITUTUP sementara.');
        $this->assertFalse($this->training->fresh()->is_attendance_active);

        // Dibuka lagi tanpa pilihan waktu: terbuka tanpa batas, jam tutup lama tidak terbawa.
        $this->toggle()->assertSessionHas('success', 'Presensi kehadiran pelatihan telah DIBUKA untuk seluruh peserta!');
        $training = $this->training->fresh();
        $this->assertTrue($training->isAttendanceOpen());
        $this->assertNull($training->attendance_closes_at);
    }

    public function test_turning_attendance_off_clears_its_schedule(): void
    {
        $this->toggle(['open' => '1', 'open_mode' => 'clock', 'open_time' => '09:40', 'close_mode' => 'clock', 'close_time' => '09:50'])->assertSessionHas('success');

        $this->actingAs($this->admin)->post(route('training.toggle-attendance-use', $this->training))->assertSessionHas('success');
        $training = $this->training->fresh();
        $this->assertFalse($training->has_attendance);
        $this->assertFalse($training->is_attendance_active);
        $this->assertNull($training->attendance_opens_at);
        $this->assertNull($training->attendance_closes_at);

        // Tanpa presensi tidak bisa dibuka atau dijadwalkan.
        $this->toggle(['open' => '1'])->assertSessionHas('error');
        $this->actingAs($this->admin)->get(route('training.show', $training))->assertOk()->assertSee('Presensi tidak dipakai di pelatihan ini')->assertSee('Pakai Presensi');
    }

    public function test_training_form_switch_keeps_a_schedule_made_in_session_controls(): void
    {
        $this->toggle(['open' => '1', 'open_mode' => 'clock', 'open_time' => '09:40', 'close_mode' => 'clock', 'close_time' => '09:50'])->assertSessionHas('success');
        $payload = [
            'title' => $this->training->title, 'trainer_id' => $this->admin->id, 'training_date' => now()->toDateString(),
            'start_time' => '09:00', 'end_time' => '10:00', 'zoom_link' => 'https://zoom.us/test', 'status' => 'scheduled',
            'participants' => $this->training->participants()->pluck('user_id')->all(),
        ];

        // Form pelatihan menampilkan presensi terjadwal sebagai "buka"; menyimpannya apa adanya tidak menghapus jadwal.
        $this->actingAs($this->admin)->get(route('training.edit', $this->training))->assertOk()
            ->assertSee('name="is_attendance_active" value="1" checked', false);
        $this->actingAs($this->admin)->put(route('training.update', $this->training), [...$payload, 'is_attendance_active' => '1'])->assertSessionHasNoErrors();
        $this->assertSame('09:50:00', $this->training->fresh()->attendance_closes_at->format('H:i:s'));

        // Saklar dimatikan: presensi tertutup dan jadwalnya terhapus.
        $this->actingAs($this->admin)->put(route('training.update', $this->training), [...$payload, 'is_attendance_active' => '0'])->assertSessionHasNoErrors();
        $training = $this->training->fresh();
        $this->assertFalse($training->is_attendance_active);
        $this->assertNull($training->attendance_opens_at);

        // Dinyalakan dari form: terbuka sekarang tanpa batas waktu.
        $this->actingAs($this->admin)->put(route('training.update', $this->training), [...$payload, 'is_attendance_active' => '1'])->assertSessionHasNoErrors();
        $training = $this->training->fresh();
        $this->assertTrue($training->isAttendanceOpen());
        $this->assertNull($training->attendance_closes_at);
    }
}
