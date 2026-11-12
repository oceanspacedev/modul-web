<?php

namespace Tests\Feature;

use App\Models\Training;
use App\Models\TrainingParticipant;
use App\Models\TrainingQuestion;
use App\Models\TrainingQuizResult;
use App\Models\User;
use Tests\TestCase;

/**
 * Batas waktu pretest/posttest pelatihan: durasi dipilih di Aturan Kuis, jam tutup dihitung saat pemateri membuka
 * fase, dan fase tertutup sendiri begitu waktunya habis.
 */
class TrainingQuizDurationTest extends TestCase
{
    private User $admin;

    private Training $training;

    private TrainingQuestion $question;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setTime(9, 18, 0));

        $this->admin = User::where('username', 'admin')->sole();
        $this->training = Training::create([
            'title' => 'Pelatihan Berbatas Waktu', 'trainer_id' => $this->admin->id, 'training_date' => now()->toDateString(),
            'start_time' => '09:00', 'end_time' => '10:00', 'zoom_link' => 'https://zoom.us/test',
            'has_pretest' => true, 'pretest_duration_minutes' => 10, 'quiz_duration_minutes' => 10,
        ]);
        $this->question = TrainingQuestion::create([
            'training_id' => $this->training->id, 'type' => 'multiple_choice', 'question' => 'APD utama?',
            'option_a' => 'Helm', 'option_b' => 'Topi', 'correct_answer' => 'a',
        ]);
        TrainingParticipant::create([
            'training_id' => $this->training->id, 'user_id' => User::factory()->staff()->create()->id,
            'token' => 'token_durasi', 'attendance_status' => 'pending',
        ]);
    }

    private function open(string $phase): void
    {
        $this->actingAs($this->admin)
            ->post(route($phase === 'pretest' ? 'training.toggle-pretest' : 'training.toggle-quiz', $this->training), ['open' => '1'])
            ->assertSessionHas('success');
    }

    public function test_quiz_rules_save_a_time_limit_for_each_phase(): void
    {
        $this->actingAs($this->admin)->get(route('training.create'))->assertOk()->assertSee('Batas waktu bawaan sejak dibuka');

        $this->actingAs($this->admin)->post(route('training.quiz-rules', $this->training), [
            'quiz' => ['pretest' => '1', 'pretest_duration_minutes' => '15', 'posttest' => '1', 'posttest_duration_minutes' => ''],
        ])->assertSessionHasNoErrors();

        $training = $this->training->fresh();
        $this->assertSame(15, $training->pretest_duration_minutes);
        $this->assertNull($training->quiz_duration_minutes);

        $this->actingAs($this->admin)->get(route('training.show', $training))
            ->assertOk()->assertSee('terbuka 15 menit')->assertSee('tanpa batas waktu');

        $this->actingAs($this->admin)->post(route('training.quiz-rules', $training), [
            'quiz' => ['pretest' => '1', 'pretest_duration_minutes' => '0', 'posttest' => '1'],
        ])->assertSessionHasErrors('quiz.pretest_duration_minutes');
    }

    public function test_opening_a_timed_pretest_sets_when_it_closes(): void
    {
        $this->actingAs($this->admin)
            ->post(route('training.toggle-pretest', $this->training), ['open' => '1'])
            ->assertSessionHas('success', 'Pretest telah DIBUKA untuk seluruh peserta, tutup otomatis pukul 09:28.');

        $training = $this->training->fresh();
        $this->assertTrue($training->isPhaseActive('pretest'));
        $this->assertSame('09:28:00', $training->pretest_closes_at->format('H:i:s'));
        $this->assertNull($training->quiz_closes_at);

        $this->get('/training/portal/token_durasi/status')->assertJson([
            'is_pretest_active' => true, 'pretest_seconds_left' => 600, 'pretest_closes_at' => '09:28',
            'is_quiz_active' => false, 'quiz_seconds_left' => null, 'quiz_closes_at' => null,
        ]);
        $this->get('/training/portal/token_durasi')->assertOk()->assertSee('Mulai Pretest')->assertSee('data-seconds-left="600"', false);
        $this->get('/training/portal/token_durasi/pretest')->assertOk()->assertSee('ditutup otomatis pukul 09:28 WIB');
        $this->actingAs($this->admin)->get(route('training.show', $training))
            ->assertOk()->assertSee('tutup otomatis 09:28')->assertSee('+5 menit')->assertSee('Atur waktu')->assertSee('Kunci Pretest');
    }

    public function test_trainer_can_open_a_phase_until_a_fixed_clock_time(): void
    {
        // Durasi di Aturan Kuis 10 menit, tetapi pemateri memilih jam tutup pasti saat membuka.
        $this->actingAs($this->admin)
            ->post(route('training.toggle-pretest', $this->training), ['open' => '1', 'close_mode' => 'clock', 'close_time' => '09:49'])
            ->assertSessionHas('success', 'Pretest telah DIBUKA untuk seluruh peserta, tutup otomatis pukul 09:49.');

        $this->assertSame('09:49:00', $this->training->fresh()->pretest_closes_at->format('H:i:s'));
        $this->get('/training/portal/token_durasi/status')->assertJson(['is_pretest_active' => true, 'pretest_seconds_left' => 31 * 60, 'pretest_closes_at' => '09:49']);

        // Pukul 09.49 tertutup untuk semua; yang belum mengerjakan tidak punya nilai.
        $this->travelTo(now()->setTime(9, 49, 0));
        $this->get('/training/portal/token_durasi/status')->assertJson(['is_pretest_active' => false]);
        $this->get('/training/portal/token_durasi/pretest')->assertRedirect('/training/portal/token_durasi');
        $this->assertSame(0, TrainingQuizResult::count());
    }

    public function test_fixed_clock_time_must_still_be_ahead(): void
    {
        $this->actingAs($this->admin)
            ->post(route('training.toggle-pretest', $this->training), ['open' => '1', 'close_mode' => 'clock', 'close_time' => '09:10'])
            ->assertSessionHasErrors(['close_time' => 'Jam tutup 09:10 sudah lewat (sekarang pukul 09:18). Isi jam yang lebih dari sekarang.']);
        $this->actingAs($this->admin)
            ->post(route('training.toggle-pretest', $this->training), ['open' => '1', 'close_mode' => 'clock', 'close_time' => ''])
            ->assertSessionHasErrors('close_time');

        $this->assertFalse($this->training->fresh()->is_pretest_active);
    }

    public function test_trainer_can_open_for_any_number_of_minutes_or_without_limit(): void
    {
        $this->actingAs($this->admin)
            ->post(route('training.toggle-pretest', $this->training), ['open' => '1', 'close_mode' => 'minutes', 'close_minutes' => '7'])
            ->assertSessionHas('success');
        $this->assertSame('09:25:00', $this->training->fresh()->pretest_closes_at->format('H:i:s'));

        // "Tanpa batas waktu" mengalahkan durasi bawaan dari Aturan Kuis.
        $this->actingAs($this->admin)
            ->post(route('training.toggle-quiz', $this->training), ['open' => '1', 'close_mode' => 'none'])
            ->assertSessionHas('success', 'Kuis pelatihan telah DIBUKA untuk seluruh peserta!');
        $training = $this->training->fresh();
        $this->assertTrue($training->isPhaseActive('posttest'));
        $this->assertNull($training->quiz_closes_at);
        $this->actingAs($this->admin)->get(route('training.show', $training))->assertOk()->assertSee('Kuis aktif &middot; tanpa batas waktu', false);
    }

    public function test_trainer_can_schedule_a_phase_to_open_and_close_by_itself(): void
    {
        // Pukul 09.18 pemateri menjadwalkan: buka 09.40, tutup 09.50.
        $this->actingAs($this->admin)->post(route('training.toggle-pretest', $this->training), [
            'open' => '1', 'open_mode' => 'clock', 'open_time' => '09:40', 'close_mode' => 'clock', 'close_time' => '09:50',
        ])->assertSessionHas('success', 'Pretest DIJADWALKAN buka pukul 09:40 dan tutup otomatis pukul 09:50.');

        $training = $this->training->fresh();
        $this->assertSame('09:40:00', $training->pretest_opens_at->format('H:i:s'));
        $this->assertSame('09:50:00', $training->pretest_closes_at->format('H:i:s'));
        $this->assertFalse($training->isPhaseActive('pretest'));

        // Belum waktunya: peserta melihat jam bukanya dan belum bisa masuk.
        $this->get('/training/portal/token_durasi/status')->assertJson([
            'is_pretest_active' => false, 'pretest_opens_at' => 'pukul 09:40', 'pretest_seconds_left' => null,
        ]);
        $this->get('/training/portal/token_durasi')->assertOk()->assertSee('Pretest dibuka otomatis pukul 09:40 WIB.')->assertDontSee('Mulai Pretest');
        $this->get('/training/portal/token_durasi/pretest')->assertRedirect('/training/portal/token_durasi');
        $this->actingAs($this->admin)->get(route('training.show', $training))
            ->assertOk()->assertSee('Terjadwal buka pukul 09:40')->assertSee('tutup 09:50')->assertSee('Ubah jadwal')->assertSee('Batalkan jadwal');

        // 09.40: terbuka sendiri.
        $this->travelTo(now()->setTime(9, 40, 0));
        $this->get('/training/portal/token_durasi/status')->assertJson([
            'is_pretest_active' => true, 'pretest_opens_at' => null, 'pretest_seconds_left' => 600, 'pretest_closes_at' => '09:50',
        ]);
        $this->get('/training/portal/token_durasi')->assertOk()->assertSee('Mulai Pretest');
        $this->get('/training/portal/token_durasi/pretest')->assertOk();

        // 09.50: tertutup sendiri.
        $this->travelTo(now()->setTime(9, 50, 0));
        $this->get('/training/portal/token_durasi/status')->assertJson(['is_pretest_active' => false, 'pretest_opens_at' => null]);
        $this->get('/training/portal/token_durasi/pretest')->assertRedirect('/training/portal/token_durasi');
    }

    public function test_scheduled_phase_counts_minutes_from_its_opening_and_can_be_cancelled(): void
    {
        // Tanpa pilihan jam tutup, batas waktu bawaan (10 menit) dihitung dari jam buka.
        $this->actingAs($this->admin)->post(route('training.toggle-quiz', $this->training), ['open' => '1', 'open_mode' => 'clock', 'open_time' => '09:40'])
            ->assertSessionHas('success', 'Kuis pelatihan DIJADWALKAN buka pukul 09:40 dan tutup otomatis pukul 09:50.');

        $this->actingAs($this->admin)->post(route('training.toggle-quiz', $this->training), [
            'open' => '1', 'open_mode' => 'clock', 'open_time' => '09:40', 'close_mode' => 'minutes', 'close_minutes' => '25',
        ])->assertSessionHas('success');
        $this->assertSame('10:05:00', $this->training->fresh()->quiz_closes_at->format('H:i:s'));

        // Jam tutup harus setelah jam buka.
        $this->actingAs($this->admin)->post(route('training.toggle-quiz', $this->training), [
            'open' => '1', 'open_mode' => 'clock', 'open_time' => '09:40', 'close_mode' => 'clock', 'close_time' => '09:30',
        ])->assertSessionHasErrors(['close_time' => 'Jam tutup 09:30 harus setelah jam buka 09:40.']);

        // "Batalkan jadwal" mengunci lagi.
        $this->actingAs($this->admin)->post(route('training.toggle-quiz', $this->training), ['open' => '0'])->assertSessionHas('success');
        $training = $this->training->fresh();
        $this->assertFalse($training->is_quiz_active);
        $this->assertNull($training->quiz_opens_at);
        $this->assertNull($training->quiz_closes_at);
        $this->travelTo(now()->setTime(9, 45, 0));
        $this->assertFalse($training->fresh()->isPhaseActive('posttest'));
    }

    public function test_opening_time_that_already_passed_opens_right_away(): void
    {
        $this->actingAs($this->admin)->post(route('training.toggle-pretest', $this->training), [
            'open' => '1', 'open_mode' => 'clock', 'open_time' => '09:10', 'close_mode' => 'clock', 'close_time' => '09:50',
        ])->assertSessionHas('success', 'Pretest telah DIBUKA untuk seluruh peserta, tutup otomatis pukul 09:50.');

        $training = $this->training->fresh();
        $this->assertNull($training->pretest_opens_at);
        $this->assertTrue($training->isPhaseActive('pretest'));
    }

    public function test_schedule_made_before_the_training_day_uses_the_training_date(): void
    {
        $this->training->update(['training_date' => now()->addDay()->toDateString()]);

        $this->actingAs($this->admin)->post(route('training.toggle-pretest', $this->training), [
            'open' => '1', 'open_mode' => 'clock', 'open_time' => '09:00', 'close_mode' => 'clock', 'close_time' => '09:10',
        ])->assertSessionHas('success');

        $training = $this->training->fresh();
        $this->assertSame(now()->addDay()->format('Y-m-d').' 09:00', $training->pretest_opens_at->format('Y-m-d H:i'));
        $this->assertSame(now()->addDay()->format('Y-m-d').' 09:10', $training->pretest_closes_at->format('Y-m-d H:i'));
        $this->assertFalse($training->isPhaseActive('pretest'));

        $this->travelTo(now()->addDay()->setTime(9, 5, 0));
        $this->assertTrue($training->fresh()->isPhaseActive('pretest'));
    }

    public function test_trainer_can_change_the_closing_time_while_a_phase_is_open(): void
    {
        $this->open('pretest');

        $this->actingAs($this->admin)
            ->post(route('training.quiz-deadline', $this->training), ['phase' => 'pretest', 'close_mode' => 'clock', 'close_time' => '09:49'])
            ->assertSessionHas('success', 'Pretest tutup otomatis pukul 09:49.');
        $this->assertSame('09:49:00', $this->training->fresh()->pretest_closes_at->format('H:i:s'));

        $this->actingAs($this->admin)
            ->post(route('training.quiz-deadline', $this->training), ['phase' => 'pretest', 'close_mode' => 'none'])
            ->assertSessionHas('success');
        $training = $this->training->fresh();
        $this->assertNull($training->pretest_closes_at);
        $this->assertTrue($training->isPhaseActive('pretest'));

        // Posttest belum dibuka: tidak ada jam tutup untuk diatur.
        $this->actingAs($this->admin)
            ->post(route('training.quiz-deadline', $this->training), ['phase' => 'posttest', 'close_mode' => 'clock', 'close_time' => '09:49'])
            ->assertSessionHas('error');
        $this->assertFalse($this->training->fresh()->is_quiz_active);

        $this->actingAs(User::factory()->staff()->create())->from('/my-trainings')
            ->post(route('training.quiz-deadline', $this->training), ['phase' => 'pretest', 'close_mode' => 'minutes', 'close_minutes' => '5'])
            ->assertSessionHas('error');
        $this->assertNull($this->training->fresh()->pretest_closes_at);
    }

    public function test_timed_pretest_closes_by_itself_when_time_runs_out(): void
    {
        $this->open('pretest');

        $this->travel(9)->minutes();
        $this->get('/training/portal/token_durasi/status')->assertJson(['is_pretest_active' => true, 'pretest_seconds_left' => 60]);
        $this->get('/training/portal/token_durasi/pretest')->assertOk();

        $this->travel(1)->minutes();
        $this->assertFalse($this->training->fresh()->isPhaseActive('pretest'));
        $this->get('/training/portal/token_durasi/status')->assertJson(['is_pretest_active' => false, 'pretest_seconds_left' => null, 'pretest_timed_out' => true]);
        $this->get('/training/portal/token_durasi')->assertOk()->assertSee('Waktu pengerjaan pretest sudah habis.')->assertDontSee('Mulai Pretest');
        $this->get('/training/portal/token_durasi/pretest')->assertRedirect('/training/portal/token_durasi');
        $this->actingAs($this->admin)->get(route('training.show', $this->training))
            ->assertOk()->assertSee('Waktu habis, pretest ditutup otomatis')->assertSee('Buka Pretest')->assertDontSee('Kunci Pretest');
    }

    public function test_phase_without_time_limit_stays_open_until_locked(): void
    {
        $this->training->update(['pretest_duration_minutes' => null]);
        $this->open('pretest');

        $this->assertNull($this->training->fresh()->pretest_closes_at);

        $this->travel(3)->hours();
        $this->assertTrue($this->training->fresh()->isPhaseActive('pretest'));
        $this->get('/training/portal/token_durasi/status')->assertJson(['is_pretest_active' => true, 'pretest_seconds_left' => null]);
        $this->get('/training/portal/token_durasi/pretest')->assertOk()->assertDontSee('ditutup otomatis pukul');
    }

    public function test_lock_button_pressed_after_time_ran_out_does_not_reopen(): void
    {
        $this->open('pretest');
        $this->travel(11)->minutes();

        // Halaman pemateri masih menampilkan "Kunci Pretest" sesaat setelah waktu habis.
        $this->actingAs($this->admin)->post(route('training.toggle-pretest', $this->training), ['open' => '0'])->assertSessionHas('success', 'Pretest telah DITUTUP.');
        $training = $this->training->fresh();
        $this->assertFalse($training->is_pretest_active);
        $this->assertNull($training->pretest_closes_at);

        // Dibuka lagi: jendela waktu baru dihitung dari sekarang.
        $this->open('pretest');
        $this->assertSame('09:39:00', $this->training->fresh()->pretest_closes_at->format('H:i:s'));
    }

    public function test_toggle_without_intent_reopens_a_phase_whose_time_ran_out(): void
    {
        $this->open('pretest');
        $this->travel(11)->minutes();

        $this->actingAs($this->admin)->post(route('training.toggle-pretest', $this->training))->assertSessionHas('success');

        $this->assertTrue($this->training->fresh()->isPhaseActive('pretest'));
    }

    public function test_trainer_can_add_time_while_a_timed_phase_is_open(): void
    {
        $this->open('pretest');

        $this->actingAs($this->admin)->post(route('training.extend-quiz-time', $this->training), ['phase' => 'pretest', 'minutes' => 5])
            ->assertSessionHas('success', 'Pretest ditambah 5 menit, tutup otomatis pukul 09:33.');
        $this->assertSame('09:33:00', $this->training->fresh()->pretest_closes_at->format('H:i:s'));
        $this->get('/training/portal/token_durasi/status')->assertJson(['pretest_seconds_left' => 900]);

        // Posttest belum dibuka, jadi tidak ada waktu untuk ditambah.
        $this->actingAs($this->admin)->post(route('training.extend-quiz-time', $this->training), ['phase' => 'posttest', 'minutes' => 5])
            ->assertSessionHas('error');
        $this->assertNull($this->training->fresh()->quiz_closes_at);

        // Setelah waktu habis juga tidak bisa ditambah; pemateri membukanya lagi.
        $this->travel(16)->minutes();
        $this->actingAs($this->admin)->post(route('training.extend-quiz-time', $this->training), ['phase' => 'pretest', 'minutes' => 5])
            ->assertSessionHas('error');
    }

    public function test_staff_cannot_add_time(): void
    {
        $this->open('pretest');

        $this->actingAs(User::factory()->staff()->create())->from('/my-trainings')
            ->post(route('training.extend-quiz-time', $this->training), ['phase' => 'pretest', 'minutes' => 5])
            ->assertSessionHas('error');

        $this->assertSame('09:28:00', $this->training->fresh()->pretest_closes_at->format('H:i:s'));
    }

    public function test_timed_posttest_closes_the_quiz_and_its_evaluation_form(): void
    {
        $this->training->update(['has_evaluation' => true]);
        $this->open('posttest');

        $this->get('/training/portal/token_durasi/status')->assertJson([
            'is_quiz_active' => true, 'quiz_seconds_left' => 600, 'quiz_closes_at' => '09:28', 'is_evaluation_open' => true,
        ]);

        $this->travel(10)->minutes();
        $this->get('/training/portal/token_durasi/status')->assertJson([
            'is_quiz_active' => false, 'quiz_seconds_left' => null, 'is_evaluation_open' => false,
        ]);
        $this->get('/training/portal/token_durasi')->assertOk()->assertSee('Waktu pengerjaan kuis sudah habis.');
        $this->get('/training/portal/token_durasi/quiz')->assertRedirect('/training/portal/token_durasi');
    }

    public function test_answers_collected_when_time_runs_out_are_still_saved(): void
    {
        $this->open('pretest');
        $this->get('/training/portal/token_durasi/pretest')->assertOk();

        // Halaman kuis mengumpulkan sendiri tepat setelah waktu habis.
        $this->travel(10)->minutes();
        $this->travel(2)->seconds();
        $this->post('/training/portal/token_durasi/pretest', ['answers' => [$this->question->id => 'a'], 'is_time_up' => '1'])
            ->assertRedirect('/training/portal/token_durasi/pretest/result')
            ->assertSessionHas('warning', 'Waktu pengerjaan habis. Jawaban yang sudah Anda isi otomatis dikumpulkan.');

        $result = TrainingQuizResult::where('phase', 'pretest')->sole();
        $this->assertSame(100.0, $result->score);
    }

    public function test_turning_a_phase_off_clears_its_closing_time(): void
    {
        $this->open('pretest');

        $this->actingAs($this->admin)->post(route('training.quiz-rules', $this->training), [
            'quiz' => ['pretest' => '0', 'posttest' => '1', 'pretest_duration_minutes' => '10'],
        ])->assertSessionHasNoErrors();

        $training = $this->training->fresh();
        $this->assertFalse($training->is_pretest_active);
        $this->assertNull($training->pretest_closes_at);
    }

    public function test_game_mode_quiz_page_carries_the_time_limit(): void
    {
        $this->training->update(['quiz_mode' => 'game']);
        $this->open('posttest');

        $this->get('/training/portal/token_durasi/quiz')->assertOk()
            ->assertSee('ditutup otomatis pukul')->assertSee('09:28 WIB')->assertSee('startQuizDeadline', false);
    }
}
