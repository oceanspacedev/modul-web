<?php

namespace Tests\Feature;

use App\Exports\TrainingRekapExport;
use App\Models\Training;
use App\Models\TrainingEvaluationQuestion;
use App\Models\TrainingParticipant;
use App\Models\TrainingQuestion;
use App\Models\User;
use Tests\TestCase;

/**
 * Satu pelatihan dari awal sampai selesai, lewat rute yang dipakai pemateri dan peserta. Di tiap tahap, halaman
 * pemateri (Kontrol Sesi), "Pelatihan Saya" dan link portal peserta, serta Dashboard harus menceritakan hal yang sama.
 */
class TrainingJourneyTest extends TestCase
{
    private const PORTAL = '/training/portal/token_perjalanan';

    private User $admin;

    private User $staff;

    private Training $training;

    private TrainingQuestion $question;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setTime(9, 0, 0));

        $this->admin = User::where('username', 'admin')->sole();
        $this->staff = User::factory()->staff()->create(['nama_lengkap' => 'Rina Peserta']);
        $this->training = Training::create([
            'title' => 'Sosialisasi SOP Gudang', 'trainer_id' => $this->admin->id, 'training_date' => now()->toDateString(),
            'start_time' => '09:00', 'end_time' => '10:30', 'zoom_link' => 'https://zoom.us/test', 'setup_completed_at' => now(),
            'has_pretest' => true, 'has_posttest' => true, 'has_evaluation' => true,
        ]);
        $this->question = TrainingQuestion::create([
            'training_id' => $this->training->id, 'type' => 'multiple_choice', 'question' => 'APD utama?',
            'option_a' => 'Helm', 'option_b' => 'Topi', 'correct_answer' => 'a',
        ]);
        TrainingParticipant::create([
            'training_id' => $this->training->id, 'user_id' => $this->staff->id, 'token' => 'token_perjalanan', 'attendance_status' => 'pending',
        ]);
    }

    private function trainer(string $route, array $data = [])
    {
        return $this->actingAs($this->admin)->post(route($route, $this->training), $data);
    }

    /** Baris pelatihan ini di "Pelatihan Saya" peserta: langkah berikutnya dan tautan ke portalnya. */
    private function myTrainings()
    {
        return $this->actingAs($this->staff)->get(route('training.my-trainings'))->assertOk()
            ->assertSee('Sosialisasi SOP Gudang')->assertSee('href="'.self::PORTAL.'"', false);
    }

    private function trainerPage()
    {
        return $this->actingAs($this->admin)->get(route('training.show', $this->training))->assertOk();
    }

    public function test_trainer_participant_and_dashboard_agree_through_a_whole_training(): void
    {
        // ── Sebelum sesi: belum ada yang dibuka ──────────────────────────────────────────────────────────────
        $this->myTrainings()->assertSee('Menunggu pemateri')->assertSee('Belum Konfirmasi');
        $this->get(self::PORTAL)->assertOk()
            ->assertSeeInOrder(['Langkah 1</span> Pretest', 'Langkah 2</span> Ikuti Sesi', 'Langkah 3</span> Presensi Kehadiran', 'Langkah 4</span> Evaluasi Pelatihan', 'Langkah 5</span> Posttest'], false)
            ->assertSee('Pretest belum dibuka oleh pemateri.')->assertSee('id="attendanceBody" data-state="locked"', false);
        $this->actingAs($this->admin)->get(route('dashboard'))->assertOk()->assertSee('Sosialisasi SOP Gudang')->assertSee('0 berlangsung');

        // ── 09.00 sesi dimulai: status Berlangsung tidak membuka apa pun sendiri ─────────────────────────────────
        $this->trainer('training.status', ['status' => 'ongoing'])->assertSessionHas('success');
        $this->actingAs($this->admin)->get(route('dashboard'))->assertSee('1 berlangsung');
        $this->myTrainings()->assertSee('Menunggu pemateri');
        $this->get(self::PORTAL.'/status')->assertJson(['is_pretest_active' => false, 'is_attendance_active' => false, 'is_evaluation_open' => false, 'is_quiz_active' => false]);
        // Evaluasi dijadwalkan pemateri buka 09.50, setelah materi.
        $this->trainer('training.toggle-evaluation-access', ['open' => '1', 'open_mode' => 'clock', 'open_time' => '09:50', 'close_mode' => 'none'])->assertSessionHas('success');
        $this->myTrainings()->assertSee('Menunggu pemateri');

        // ── Pretest 10 menit ────────────────────────────────────────────────────────────────────────────────
        $this->trainer('training.toggle-pretest', ['open' => '1', 'close_mode' => 'minutes', 'close_minutes' => '10'])->assertSessionHas('success');
        $this->myTrainings()->assertSee('Kerjakan pretest');
        $this->get(self::PORTAL.'/status')->assertJson(['is_pretest_active' => true, 'pretest_closes_at' => '09:10', 'is_attendance_active' => false, 'is_quiz_active' => false]);
        $this->get(self::PORTAL)->assertSee('Mulai Pretest');
        $this->trainerPage()->assertSee('Pretest aktif')->assertSee('tutup otomatis 09:10');

        $this->post(self::PORTAL.'/pretest', ['answers' => [$this->question->id => 'b']])->assertRedirect(self::PORTAL.'/pretest/result');
        $this->myTrainings()->assertSee('Menunggu pemateri')->assertDontSee('Kerjakan pretest');

        // ── 09.10 presensi dibuka sampai 09.20 ────────────────────────────────────────────────────────────────
        $this->travelTo(now()->setTime(9, 10, 0));
        $this->trainer('training.toggle-attendance', ['open' => '1', 'close_mode' => 'clock', 'close_time' => '09:20'])->assertSessionHas('success');
        $this->myTrainings()->assertSee('Isi presensi')->assertSee('Belum Konfirmasi');
        $this->get(self::PORTAL.'/status')->assertJson(['is_attendance_active' => true, 'attendance_closes_at' => '09:20', 'attendance_seconds_left' => 600, 'is_pretest_active' => false]);
        $this->trainerPage()->assertSee('Presensi aktif')->assertSee('Waktu habis, pretest ditutup otomatis');

        $this->post(self::PORTAL.'/attendance', ['status' => 'hadir'])->assertSessionHas('success');
        $this->myTrainings()->assertSee('Hadir')->assertDontSee('Isi presensi')->assertSee('Menunggu pemateri');

        // 09.20 presensi tertutup sendiri; peserta yang sudah hadir tidak terpengaruh.
        $this->travelTo(now()->setTime(9, 20, 0));
        $this->get(self::PORTAL.'/status')->assertJson(['is_attendance_active' => false, 'attendance_timed_out' => true, 'attendance_status' => 'hadir']);
        $this->trainerPage()->assertSee('Waktu habis, presensi ditutup otomatis');

        // ── 09.50 form evaluasi terbuka sendiri, sebelum posttest dibuka ────────────────────────────────────────
        $this->travelTo(now()->setTime(9, 50, 0));
        $this->myTrainings()->assertSee('Isi evaluasi');
        $this->get(self::PORTAL.'/status')->assertJson(['is_evaluation_open' => true, 'is_quiz_active' => false]);
        $answers = $this->training->evaluationQuestions()->get()
            ->mapWithKeys(fn (TrainingEvaluationQuestion $question) => [$question->id => $question->isScale() ? 5 : 'Jelas dan runtut.'])->all();
        // Posttest belum dibuka, jadi setelah mengirim peserta kembali ke portal, bukan ke kuis.
        $this->post(self::PORTAL.'/evaluation', ['answers' => $answers])->assertRedirect(self::PORTAL)->assertSessionHas('success');
        $this->myTrainings()->assertSee('Menunggu pemateri')->assertDontSee('Isi evaluasi');

        // ── 09.55 posttest 10 menit: waktunya utuh karena evaluasi sudah terisi ───────────────────────────────
        $this->travelTo(now()->setTime(9, 55, 0));
        $this->trainer('training.toggle-quiz', ['open' => '1', 'close_mode' => 'minutes', 'close_minutes' => '10'])->assertSessionHas('success');
        $this->myTrainings()->assertSee('Kerjakan posttest');
        $this->get(self::PORTAL.'/status')->assertJson(['is_quiz_active' => true, 'quiz_seconds_left' => 600, 'quiz_closes_at' => '10:05']);
        $this->get(self::PORTAL)->assertSee('Mulai Kerjakan Kuis')->assertDontSee('Isi Evaluasi, lalu Kerjakan Kuis');
        $this->get(self::PORTAL.'/quiz')->assertOk()->assertSee('APD utama?');
        $this->post(self::PORTAL.'/quiz', ['answers' => [$this->question->id => 'a']])->assertRedirect(self::PORTAL.'/result');

        // ── Selesai: ketiga tempat menampilkan hasil yang sama ────────────────────────────────────────────────
        $this->myTrainings()->assertSee('Selesai')->assertSee('Hadir')->assertSee('<strong>100</strong>', false)->assertSee('Pretest 0');
        $this->get(self::PORTAL)->assertOk()->assertSee('Kehadiran Anda Telah Terverifikasi')->assertSee('Terima kasih, evaluasi Anda sudah tersimpan');
        $this->trainerPage()->assertSee('Rina Peserta')->assertSee('<dt>Posttest</dt>', false)->assertSeeInOrder(['participant-detail-label">Pretest</h6>', 'participant-detail-value">0</span>'], false)->assertSee('1 dari 1 mengisi');
        $this->actingAs($this->admin)->getJson(route('training.live-stats', $this->training))
            ->assertJson(['attended' => 1, 'quizSubmitted' => 1, 'avgScore' => 100, 'pretestSubmitted' => 1, 'pretestAvg' => 0]);
        // Dashboard menghitung nilai utama (posttest), bukan pretest.
        $this->actingAs($this->admin)->get(route('dashboard'))->assertOk()
            ->assertSee('Rina Peserta')->assertSeeInOrder(['Rina Peserta', 'Sosialisasi SOP Gudang', 'badge-success">100<'], false);

        // Rekap: kolom mengikuti langkah yang dipakai, dalam urutan langkahnya.
        $training = Training::with(['trainer', 'participants.user.divisi', 'participants.quizResult', 'participants.pretestResult'])->findOrFail($this->training->id);
        $rows = (new TrainingRekapExport($training))->array();
        $header = collect($rows)->search(fn ($row) => ($row[0] ?? null) === 'No');
        $row = array_combine($rows[$header], $rows[$header + 1]);
        $this->assertSame(
            ['Sudah Mengerjakan', 0.0, 'Hadir', 'Sudah Mengisi', 5.0, 'Sudah Mengerjakan', 100.0],
            [$row['Status Pretest'], $row['Nilai Pretest (0-100)'], $row['Status Kehadiran'], $row['Status Evaluasi'], $row['Rata-rata Evaluasi (1-5)'], $row['Status Posttest'], $row['Nilai Posttest (0-100)']],
        );
        $this->assertSame(
            ['Status Pretest', 'Status Kehadiran', 'Status Evaluasi', 'Status Posttest'],
            array_values(array_filter(array_keys($row), fn (string $heading) => in_array($heading, ['Status Pretest', 'Status Kehadiran', 'Status Evaluasi', 'Status Posttest'], true))),
        );
    }
}
