<?php

namespace Tests\Feature;

use App\Enums\TrainingStatus;
use App\Models\Training;
use App\Models\TrainingEvaluation;
use App\Models\TrainingEvaluationQuestion;
use App\Models\TrainingParticipant;
use App\Models\TrainingQuestion;
use App\Models\TrainingQuizResult;
use App\Models\User;
use Tests\TestCase;

/**
 * Pemateri mengatur urutan langkah peserta (ikuti sesi, pretest, presensi, form evaluasi, posttest) per pelatihan.
 * Urutan itu menentukan susunan dan nomor kartu di portal, langkah berikutnya peserta, daftar langkah pemateri, dan
 * apakah form evaluasi wajib diisi sebelum posttest.
 */
class TrainingStepOrderTest extends TestCase
{
    private const PORTAL = '/training/portal/token_urutan';

    private User $admin;

    private Training $training;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::where('username', 'admin')->sole();
        $this->training = Training::create([
            'title' => 'Sosialisasi SOP', 'trainer_id' => $this->admin->id, 'training_date' => now()->toDateString(),
            'start_time' => '11:10', 'end_time' => '11:30', 'zoom_link' => 'https://zoom.us/test',
            'status' => TrainingStatus::Ongoing, 'setup_completed_at' => now(),
            'has_pretest' => true, 'has_evaluation' => true,
        ]);
        TrainingQuestion::create([
            'training_id' => $this->training->id, 'type' => 'multiple_choice', 'question' => 'APD utama?',
            'option_a' => 'Helm', 'option_b' => 'Topi', 'correct_answer' => 'a',
        ]);
        TrainingParticipant::create([
            'training_id' => $this->training->id, 'user_id' => User::factory()->staff()->create()->id,
            'token' => 'token_urutan', 'attendance_status' => 'pending',
        ]);
    }

    /**
     * @param  list<string>  $steps
     */
    private function saveOrder(array $steps)
    {
        return $this->actingAs($this->admin)->post(route('training.portal-steps', $this->training), ['steps' => $steps]);
    }

    private function participant(): TrainingParticipant
    {
        return $this->training->participants()->with(['training.evaluationQuestions', 'quizResult', 'pretestResult', 'evaluation'])->sole();
    }

    private function fillEvaluation(): void
    {
        $participant = $this->training->participants()->sole();
        TrainingEvaluation::create([
            'training_id' => $this->training->id, 'training_participant_id' => $participant->id, 'user_id' => $participant->user_id, 'submitted_at' => now(),
            'answers' => $this->training->evaluationQuestions()->get()->mapWithKeys(fn ($question) => [$question->id => match ($question->type) {
                TrainingEvaluationQuestion::TYPE_SCALE => 4,
                TrainingEvaluationQuestion::TYPE_CHOICE => $question->options[0],
                default => 'Bagus',
            }])->all(),
        ]);
    }

    public function test_default_order_is_unchanged(): void
    {
        $this->assertSame(['pretest', 'session', 'attendance', 'evaluation', 'posttest'], $this->training->portalSteps());
        $this->assertTrue($this->training->evaluationFollowsPosttest());

        $this->get(self::PORTAL)->assertOk()->assertSeeInOrder([
            'Langkah 1</span> Pretest', 'Langkah 2</span> Ikuti Sesi', 'Langkah 3</span> Presensi Kehadiran',
            'Langkah 4</span> Evaluasi Pelatihan', 'Langkah 5</span> Posttest',
        ], false);
        $this->actingAs($this->admin)->get(route('training.show', $this->training))->assertOk()
            ->assertSee('Urutan Langkah Peserta')->assertSee('Pretest → Ikuti sesi → Presensi → Form evaluasi → Posttest');
    }

    public function test_trainer_reorders_the_participant_steps(): void
    {
        // Join Zoom dulu, pretest, presensi, materi, evaluasi, lalu posttest.
        $this->saveOrder(['session', 'pretest', 'attendance', 'evaluation', 'posttest'])
            ->assertSessionHas('success', 'Urutan langkah peserta disimpan: Ikuti sesi → Pretest → Presensi → Form evaluasi → Posttest.');

        $this->assertSame(['session', 'pretest', 'attendance', 'evaluation', 'posttest'], $this->training->fresh()->portalSteps());
        $this->get(self::PORTAL)->assertOk()->assertSeeInOrder([
            'Langkah 1</span> Ikuti Sesi', 'Langkah 2</span> Pretest', 'Langkah 3</span> Presensi Kehadiran',
            'Langkah 4</span> Evaluasi Pelatihan', 'Langkah 5</span> Posttest',
        ], false);

        // Daftar langkah pemateri ikut: presensi sebelum pretest bila diurutkan begitu.
        $this->saveOrder(['session', 'attendance', 'pretest', 'evaluation', 'posttest'])->assertSessionHas('success');
        $this->actingAs($this->admin)->get(route('training.show', $this->training))->assertOk()->assertSeeInOrder([
            'setup-step-label">Presensi', 'setup-step-label">Pretest', 'setup-step-label">Posttest', 'setup-step-label">Rekap',
        ], false);
    }

    public function test_steps_the_training_does_not_use_are_skipped_in_the_numbering(): void
    {
        $this->saveOrder(['session', 'pretest', 'attendance', 'evaluation', 'posttest']);
        $this->training->update(['has_pretest' => false, 'has_attendance' => false]);

        $this->assertSame(['session', 'evaluation', 'posttest'], $this->training->fresh()->portalSteps());
        $this->get(self::PORTAL)->assertOk()
            ->assertSeeInOrder(['Langkah 1</span> Ikuti Sesi', 'Langkah 2</span> Evaluasi Pelatihan', 'Langkah 3</span> Kuis Evaluasi'], false)
            ->assertDontSee('id="pretestCard"', false)->assertDontSee('id="attendanceCard"', false);

        // Dinyalakan lagi: kembali ke posisi yang sudah diatur.
        $this->training->update(['has_pretest' => true]);
        $this->assertSame(['session', 'pretest', 'evaluation', 'posttest'], $this->training->fresh()->portalSteps());
    }

    public function test_session_controls_follow_the_step_order(): void
    {
        $headings = ['class="text-sm font-weight-bold">Akses Pretest', 'class="text-sm font-weight-bold">Akses Presensi', 'class="text-sm font-weight-bold">Form Evaluasi', 'class="text-sm font-weight-bold">Akses Posttest'];

        // Bawaan: pretest, presensi, evaluasi, posttest; Urutan Langkah dan Aturan Kuis tetap di bawah.
        $this->actingAs($this->admin)->get(route('training.show', $this->training))->assertOk()
            ->assertSeeInOrder([...$headings, 'class="text-sm font-weight-bold">Urutan Langkah Peserta'], false);

        $this->saveOrder(['attendance', 'session', 'posttest', 'evaluation', 'pretest'])->assertSessionHas('success');
        $this->actingAs($this->admin)->get(route('training.show', $this->training))->assertOk()
            ->assertSeeInOrder([$headings[1], 'Bukti Foto', $headings[3], $headings[2], $headings[0], 'class="text-sm font-weight-bold">Urutan Langkah Peserta'], false);
    }

    public function test_order_must_list_every_step_exactly_once(): void
    {
        $this->saveOrder(['session', 'pretest', 'attendance', 'evaluation'])->assertSessionHasErrors('steps');
        $this->saveOrder(['session', 'session', 'attendance', 'evaluation', 'posttest'])->assertSessionHasErrors('steps.0');
        $this->saveOrder(['session', 'pretest', 'attendance', 'evaluation', 'lain'])->assertSessionHasErrors('steps.4');
        $this->actingAs(User::factory()->staff()->create())->from('/my-trainings')
            ->post(route('training.portal-steps', $this->training), ['steps' => ['session', 'pretest', 'attendance', 'evaluation', 'posttest']])
            ->assertSessionHas('error');

        $this->assertNull($this->training->fresh()->portal_steps);
    }

    public function test_evaluation_before_the_posttest_must_be_filled_first(): void
    {
        // Form evaluasi dan posttest sama-sama dibuka pemateri.
        $this->training->update(['is_quiz_active' => true, 'is_evaluation_active' => true]);

        $this->get(self::PORTAL.'/quiz')->assertRedirect(self::PORTAL.'/evaluation');
        $this->assertSame('Isi evaluasi, lalu kerjakan posttest', $this->participant()->nextStep()['label']);
        $this->actingAs($this->admin)->get(route('training.show', $this->training))->assertOk()->assertSee('Wajib diisi sebelum posttest');
    }

    public function test_evaluation_after_the_posttest_no_longer_blocks_it(): void
    {
        $this->saveOrder(['session', 'pretest', 'attendance', 'posttest', 'evaluation'])->assertSessionHas('success');
        $this->training->refresh();
        $this->assertFalse($this->training->evaluationFollowsPosttest());

        // Posttest langsung bisa dikerjakan; form evaluasi berdiri sendiri (dibuka pemateri).
        $this->training->update(['is_quiz_active' => true, 'is_evaluation_active' => true]);
        $this->get(self::PORTAL.'/quiz')->assertOk();
        $this->get(self::PORTAL.'/evaluation')->assertOk();
        $this->get(self::PORTAL)->assertOk()
            ->assertSeeInOrder(['Langkah 4</span> Posttest', 'Langkah 5</span> Evaluasi Pelatihan'], false)
            ->assertSee('Mulai Kerjakan Kuis')->assertDontSee('Isi Evaluasi, lalu Kerjakan Kuis');
        $this->assertSame('Kerjakan posttest', $this->participant()->nextStep()['label']);

        // Posttest ditutup: evaluasi tetap bisa diisi karena tidak lagi menempel padanya.
        $this->training->update(['is_quiz_active' => false]);
        $this->get(self::PORTAL.'/evaluation')->assertOk();

        // Selesai posttest belum berarti selesai: evaluasinya masih ditagih.
        $participant = $this->training->participants()->sole();
        TrainingQuizResult::create([
            'training_id' => $this->training->id, 'user_id' => $participant->user_id, 'training_participant_id' => $participant->id,
            'phase' => 'posttest', 'total_questions' => 1, 'correct_answers' => 1, 'score' => 100, 'mc_score' => 100,
            'essay_status' => 'none', 'attempt_count' => 1, 'submitted_at' => now(), 'answers' => [],
        ]);
        $this->assertSame(['label' => 'Isi evaluasi', 'kind' => 'action'], $this->participant()->nextStep());
        $this->fillEvaluation();
        $this->assertSame(['label' => 'Selesai', 'kind' => 'done'], $this->participant()->nextStep());

        // Form evaluasi dibuka dan diatur waktunya sendiri, di urutan mana pun.
        $this->actingAs($this->admin)->get(route('training.show', $this->training))->assertOk()->assertSee('Atur waktu Evaluasi');
        $this->actingAs($this->admin)->post(route('training.toggle-evaluation-access', $this->training), ['open' => '1', 'close_mode' => 'none'])->assertSessionHas('success');
        $this->saveOrder(['session', 'pretest', 'attendance', 'evaluation', 'posttest']);
        $this->actingAs($this->admin)->post(route('training.toggle-evaluation-access', $this->training), ['open' => '1', 'close_mode' => 'none'])->assertSessionHas('success');
    }

    public function test_participants_next_step_follows_the_order(): void
    {
        $this->training->update(['is_pretest_active' => true, 'is_attendance_active' => true]);

        // Bawaan: pretest sebelum presensi.
        $this->assertSame('Kerjakan pretest', $this->participant()->nextStep()['label']);

        $this->saveOrder(['session', 'attendance', 'pretest', 'evaluation', 'posttest']);
        $this->assertSame('Isi presensi', $this->participant()->nextStep()['label']);
    }
}
