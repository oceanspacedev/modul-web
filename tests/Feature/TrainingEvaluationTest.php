<?php

namespace Tests\Feature;

use App\Enums\TrainingStatus;
use App\Exports\TrainingEvaluationSheet;
use App\Jobs\GenerateExportFile;
use App\Models\Training;
use App\Models\TrainingEvaluation;
use App\Models\TrainingEvaluationQuestion;
use App\Models\TrainingParticipant;
use App\Models\TrainingQuestion;
use App\Models\TrainingQuizResult;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use LogicException;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * Form evaluasi pelatihan: bisa dinyalakan/dimatikan per pelatihan, diisi peserta sebelum posttest dengan jamnya
 * sendiri (tidak memotong waktu posttest), dan tidak menanyakan data yang sudah ada di sistem (nama, jabatan,
 * divisi, tanggal, materi).
 */
class TrainingEvaluationTest extends TestCase
{
    private const PORTAL = '/training/portal/token_evaluasi';

    private User $admin;

    private User $staff;

    private Training $training;

    private TrainingParticipant $participant;

    private TrainingQuestion $question;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::where('username', 'admin')->sole();
        $this->staff = User::factory()->staff()->create(['nama_lengkap' => 'Sari Peserta']);
        $this->training = Training::create([
            'title' => 'Ketentuan Sanksi Administrasi', 'trainer_id' => $this->admin->id, 'training_date' => '2026-11-12',
            'start_time' => '09:00', 'end_time' => '10:00', 'zoom_link' => 'https://zoom.us/test',
            'has_evaluation' => true,
        ]);
        $this->question = TrainingQuestion::create([
            'training_id' => $this->training->id, 'type' => 'multiple_choice', 'question' => 'Batas toleransi terlambat?',
            'option_a' => '5 menit', 'option_b' => '30 menit', 'correct_answer' => 'a',
        ]);
        $this->participant = TrainingParticipant::create([
            'training_id' => $this->training->id, 'user_id' => $this->staff->id,
            'token' => 'token_evaluasi', 'attendance_status' => 'hadir',
        ]);
    }

    /**
     * Jawaban untuk semua pertanyaan pelatihan: $value untuk skala, $comment untuk jawaban teks.
     *
     * @return array{answers: array<int, int|string>}
     */
    private function answers(int $value = 4, string $comment = 'Materinya jelas.', ?Training $training = null): array
    {
        $answers = [];
        foreach (($training ?? $this->training)->evaluationQuestions()->get() as $question) {
            $answers[$question->id] = match ($question->type) {
                TrainingEvaluationQuestion::TYPE_SCALE => $value,
                TrainingEvaluationQuestion::TYPE_CHOICE => $question->options[0],
                default => $comment,
            };
        }

        return ['answers' => $answers];
    }

    /**
     * Pemateri membuka form evaluasi lewat dialog "Buka Evaluasi" (bawaan: sekarang, tanpa batas waktu).
     *
     * @param  array<string, string>  $choice
     */
    private function openEvaluation(array $choice = [])
    {
        return $this->actingAs($this->admin)->post(route('training.toggle-evaluation-access', $this->training), ['open' => '1', ...$choice]);
    }

    private function scaleQuestions()
    {
        return $this->training->evaluationQuestions()->where('type', TrainingEvaluationQuestion::TYPE_SCALE)->get();
    }

    private function commentQuestion(): TrainingEvaluationQuestion
    {
        return $this->training->evaluationQuestions()->where('type', TrainingEvaluationQuestion::TYPE_TEXT)->sole();
    }

    public function test_training_form_turns_the_evaluation_on_and_off(): void
    {
        $this->actingAs($this->admin)->get(route('training.create'))->assertOk()->assertSee('Form Evaluasi')->assertSee('name="has_evaluation"', false);

        $payload = [
            'title' => 'Pelatihan Baru', 'trainer_id' => $this->admin->id, 'training_date' => now()->toDateString(),
            'start_time' => '09:00', 'end_time' => '10:00', 'zoom_link' => 'https://zoom.us/test',
            'participants' => [$this->staff->id],
        ];

        $this->actingAs($this->admin)->post('/training', [...$payload, 'has_evaluation' => '1'])->assertSessionHasNoErrors();
        $training = Training::where('title', 'Pelatihan Baru')->sole();
        $this->assertTrue($training->has_evaluation);
        // Menyalakan evaluasi mengisi pertanyaan bawaan: sembilan skala dan satu masukan opsional.
        $this->assertSame(10, $training->evaluationQuestions()->count());
        $this->assertSame(9, $training->evaluationQuestions()->where('type', 'scale')->where('is_required', true)->count());

        $this->actingAs($this->admin)->put(route('training.update', $training), [...$payload, 'status' => 'scheduled', 'has_evaluation' => '0'])
            ->assertSessionHasNoErrors();
        $this->assertFalse($training->fresh()->has_evaluation);

        // Bawaan: mati.
        $this->actingAs($this->admin)->post('/training', [...$payload, 'title' => 'Tanpa Evaluasi'])->assertSessionHasNoErrors();
        $plain = Training::where('title', 'Tanpa Evaluasi')->sole();
        $this->assertFalse($plain->has_evaluation);
        $this->assertSame(0, $plain->evaluationQuestions()->count());

        // Dinyalakan belakangan dari Kontrol Sesi: pertanyaan bawaan ikut dibuat, dan tidak digandakan saat diulang.
        $this->actingAs($this->admin)->post(route('training.toggle-evaluation', $plain));
        $this->actingAs($this->admin)->post(route('training.toggle-evaluation', $plain));
        $this->actingAs($this->admin)->post(route('training.toggle-evaluation', $plain));
        $this->assertSame(10, $plain->evaluationQuestions()->count());
    }

    public function test_trainer_toggles_the_evaluation_from_session_controls(): void
    {
        $this->actingAs($this->admin)->get(route('training.show', $this->training))->assertOk()
            ->assertSee('Form Evaluasi')->assertSee('Wajib diisi sebelum posttest selama form terbuka')
            ->assertSee('Form evaluasi ditutup untuk peserta')->assertSee('Buka Evaluasi')->assertSee('Nonaktifkan');

        $this->actingAs($this->admin)->post(route('training.toggle-evaluation', $this->training))->assertSessionHas('success');
        $this->assertFalse($this->training->fresh()->has_evaluation);
        $this->actingAs($this->admin)->get(route('training.show', $this->training))
            ->assertSee('Peserta tidak diminta mengisi evaluasi')->assertDontSee('Hasil Evaluasi Peserta');

        $this->actingAs($this->admin)->post(route('training.toggle-evaluation', $this->training))->assertSessionHas('success');
        $this->assertTrue($this->training->fresh()->has_evaluation);

        // Peserta biasa tidak boleh mengubahnya.
        $this->actingAs($this->staff)->from('/my-trainings')->post(route('training.toggle-evaluation', $this->training))->assertSessionHas('error');
        $this->assertTrue($this->training->fresh()->has_evaluation);
    }

    public function test_evaluation_stays_closed_until_the_trainer_opens_it(): void
    {
        // Tidak terbuka sendiri: sesi berlangsung dan posttest dibuka pun form tetap tertutup, dan posttest tidak
        // terkunci olehnya.
        $this->training->update(['status' => TrainingStatus::Ongoing, 'is_quiz_active' => true]);
        $this->get(self::PORTAL)->assertOk()->assertSee('Evaluasi Pelatihan')->assertSee('Form evaluasi belum dibuka oleh pemateri.')
            ->assertSee('Mulai Kerjakan Kuis')->assertDontSee('Isi Evaluasi, lalu Kerjakan Kuis');
        $this->get(self::PORTAL.'/status')->assertJson(['has_evaluation' => true, 'is_evaluation_open' => false, 'is_quiz_active' => true]);
        $this->get(self::PORTAL.'/evaluation')->assertRedirect(self::PORTAL)->assertSessionHas('warning');
        $this->post(self::PORTAL.'/evaluation', $this->answers())->assertRedirect(self::PORTAL)->assertSessionHas('warning');
        $this->assertSame(0, TrainingEvaluation::count());
        $this->get(self::PORTAL.'/quiz')->assertOk();

        // Dibuka pemateri: peserta bisa mengisi, dan kuis kini menunggu evaluasinya.
        $this->openEvaluation()->assertSessionHas('success', 'Form evaluasi telah DIBUKA untuk seluruh peserta!');
        $this->get(self::PORTAL.'/status')->assertJson(['is_evaluation_open' => true, 'evaluation_seconds_left' => null]);
        $this->get(self::PORTAL)->assertSee('Isi Form Evaluasi')->assertSee('Isi Evaluasi, lalu Kerjakan Kuis')
            ->assertDontSee('href="'.self::PORTAL.'/quiz"', false);
        $this->actingAs($this->admin)->get(route('training.show', $this->training))->assertOk()
            ->assertSee('Evaluasi aktif')->assertSee('tanpa batas waktu')->assertSee('Kunci Evaluasi')->assertSee('Atur waktu Evaluasi');

        // Dikunci lagi: langsung tertutup.
        $this->actingAs($this->admin)->post(route('training.toggle-evaluation-access', $this->training), ['open' => '0'])
            ->assertSessionHas('success', 'Form evaluasi telah DITUTUP.');
        $this->get(self::PORTAL.'/evaluation')->assertRedirect(self::PORTAL);
    }

    public function test_evaluation_opened_before_the_posttest_does_not_eat_the_posttest_time(): void
    {
        // Pemateri membuka evaluasi lebih dulu: peserta mengisinya walau posttest belum dibuka.
        $this->openEvaluation()->assertSessionHas('success');
        $this->get(self::PORTAL.'/status')->assertJson(['is_quiz_active' => false, 'is_evaluation_open' => true]);
        $this->get(self::PORTAL)->assertSee('Isi Form Evaluasi');
        $this->assertSame('Isi evaluasi', $this->participant->fresh()->nextStep()['label']);

        // Diisi sebelum posttest dibuka: tidak lanjut ke kuis yang belum terbuka.
        $this->post(self::PORTAL.'/evaluation', $this->answers())->assertRedirect(self::PORTAL);

        // Saat posttest dibuka, peserta langsung mengerjakannya dengan waktu penuh.
        $this->training->openPhase(Training::PHASE_POSTTEST, now()->addMinutes(10));
        $this->get(self::PORTAL.'/quiz')->assertOk()->assertSee('Batas toleransi terlambat?');
    }

    public function test_evaluation_can_be_scheduled_and_closes_by_itself(): void
    {
        $this->travelTo(now()->setTime(9, 18, 0));
        $this->openEvaluation(['open_mode' => 'clock', 'open_time' => '09:40', 'close_mode' => 'clock', 'close_time' => '09:50'])
            ->assertSessionHas('success', 'Form evaluasi DIJADWALKAN buka pukul 09:40 dan tutup otomatis pukul 09:50.');

        // Belum waktunya.
        $this->get(self::PORTAL)->assertOk()->assertSee('Form evaluasi dibuka otomatis pukul 09:40 WIB.');
        $this->get(self::PORTAL.'/evaluation')->assertRedirect(self::PORTAL)->assertSessionHas('warning', 'Form evaluasi dibuka otomatis pukul 09:40 WIB.');
        $this->actingAs($this->admin)->get(route('training.show', $this->training))->assertOk()
            ->assertSee('Terjadwal buka pukul 09:40')->assertSee('tutup 09:50')->assertSee('Ubah jadwal')->assertSee('Batalkan jadwal');

        // 09.40: terbuka sendiri, dengan hitung mundur.
        $this->travelTo(now()->setTime(9, 40, 0));
        $this->get(self::PORTAL.'/status')->assertJson(['is_evaluation_open' => true, 'evaluation_seconds_left' => 600, 'evaluation_closes_at' => '09:50']);
        $this->get(self::PORTAL)->assertOk()->assertSee('Isi Form Evaluasi')->assertSee('data-deadline="evaluation" data-seconds-left="600"', false);
        $this->actingAs($this->admin)->post(route('training.extend-quiz-time', $this->training), ['phase' => 'evaluation', 'minutes' => '5'])
            ->assertSessionHas('success', 'Form Evaluasi ditambah 5 menit, tutup otomatis pukul 09:55.');

        // 09.55: tertutup sendiri; kiriman yang sedang diketik masih diterima sebentar.
        $this->travelTo(now()->setTime(9, 55, 0));
        $this->get(self::PORTAL.'/status')->assertJson(['is_evaluation_open' => false]);
        $this->get(self::PORTAL)->assertOk()->assertSee('Waktu pengisian form evaluasi sudah habis.');
        $this->actingAs($this->admin)->get(route('training.show', $this->training))->assertOk()->assertSee('Waktu habis, evaluasi ditutup otomatis');
        $this->post(self::PORTAL.'/evaluation', $this->answers())->assertSessionHas('success');
    }

    public function test_evaluation_hours_do_not_lock_the_posttest_once_they_ran_out(): void
    {
        $this->training->update(['is_quiz_active' => true]);

        $this->openEvaluation(['close_mode' => 'minutes', 'close_minutes' => '15'])->assertSessionHas('success');
        $this->assertTrue($this->training->fresh()->isEvaluationOpen());
        $this->get(self::PORTAL.'/quiz')->assertRedirect(self::PORTAL.'/evaluation');

        // Jam evaluasi habis: posttest yang masih terbuka tidak terkunci olehnya.
        $this->travel(20)->minutes();
        $this->assertFalse($this->training->fresh()->isEvaluationOpen());
        $this->get(self::PORTAL.'/quiz')->assertOk();

        // Posttest selesai tanpa evaluasi belum berarti selesai: begitu form dibuka lagi, evaluasinya ditagih.
        $this->post(self::PORTAL.'/quiz', ['answers' => [$this->question->id => 'a']])->assertRedirect(self::PORTAL.'/result');
        $next = fn () => TrainingParticipant::with(['training.evaluationQuestions', 'quizResult', 'pretestResult', 'evaluation'])->findOrFail($this->participant->id)->nextStep();
        $this->assertNotSame('done', $next()['kind']);
        $this->openEvaluation()->assertSessionHas('success');
        $this->assertSame(['label' => 'Isi evaluasi', 'kind' => 'action'], $next());
        $this->post(self::PORTAL.'/evaluation', $this->answers())->assertSessionHas('success');
        $this->assertSame(['label' => 'Selesai', 'kind' => 'done'], $next());
    }

    public function test_evaluation_cannot_be_opened_while_it_is_off_or_has_no_questions(): void
    {
        $this->training->evaluationQuestions()->delete();
        $this->openEvaluation()->assertSessionHas('error', 'Form evaluasi belum bisa dibuka: tambahkan pertanyaannya dulu lewat Kelola pertanyaan.');
        $this->actingAs($this->admin)->get(route('training.show', $this->training))->assertOk()
            ->assertSee('Belum ada pertanyaan, jadi form belum bisa dibuka')->assertSee('Buat pertanyaan dulu');

        $this->training->update(['has_evaluation' => false]);
        $this->openEvaluation()->assertSessionHas('error', 'Form evaluasi tidak dipakai di pelatihan ini. Nyalakan dulu lewat Aktifkan.');
        $this->assertFalse($this->training->fresh()->is_evaluation_active);

        // Peserta biasa tidak boleh membukanya.
        $this->training->update(['has_evaluation' => true]);
        $this->actingAs($this->staff)->from('/my-trainings')->post(route('training.toggle-evaluation-access', $this->training), ['open' => '1'])->assertSessionHas('error');
        $this->assertFalse($this->training->fresh()->is_evaluation_active);
    }

    public function test_turning_the_evaluation_off_closes_it_and_clears_its_schedule(): void
    {
        $this->openEvaluation(['close_mode' => 'minutes', 'close_minutes' => '30'])->assertSessionHas('success');

        $this->actingAs($this->admin)->post(route('training.toggle-evaluation', $this->training))->assertSessionHas('success');
        $training = $this->training->fresh();
        $this->assertFalse($training->is_evaluation_active);
        $this->assertNull($training->evaluation_opens_at);
        $this->assertNull($training->evaluation_closes_at);

        // Dinyalakan lagi: tetap tertutup sampai pemateri membukanya.
        $this->actingAs($this->admin)->post(route('training.toggle-evaluation', $this->training))->assertSessionHas('success');
        $this->assertFalse($this->training->fresh()->isEvaluationOpen());
    }

    public function test_portal_reloads_when_the_quiz_opens_while_the_evaluation_is_still_owed(): void
    {
        // Evaluasi sudah terbuka sebelum kuis: saat kuis dibuka, kartu kuis harus dirender ulang server supaya
        // tombolnya mengarah ke form evaluasi, bukan langsung ke kuis.
        $this->openEvaluation()->assertSessionHas('success');
        $this->get(self::PORTAL)->assertOk()->assertSee('const evaluationBeforeQuiz  = true;', false);

        $this->post(self::PORTAL.'/evaluation', $this->answers());
        $this->get(self::PORTAL)->assertOk()->assertSee('const evaluationBeforeQuiz  = false;', false);
    }

    public function test_participant_fills_the_evaluation_before_the_posttest(): void
    {
        $this->training->update(['is_quiz_active' => true, 'is_evaluation_active' => true]);

        // Posttest belum bisa dibuka: peserta diarahkan ke form evaluasi.
        $this->get(self::PORTAL.'/quiz')->assertRedirect(self::PORTAL.'/evaluation');

        // Data yang sudah ada di sistem tampil terisi, bukan ditanyakan lagi.
        $form = $this->get(self::PORTAL.'/evaluation')->assertOk()
            ->assertSee('Terisi otomatis')->assertSee('Sari Peserta')->assertSee('Ketentuan Sanksi Administrasi')->assertSee('12 November 2026')
            ->assertSee('Instruksi yang diberikan pemateri jelas dan mudah dipahami')
            ->assertSee('Kirim &amp; Lanjut ke Kuis', false);
        $this->assertSame(9 * 5, substr_count($form->getContent(), 'type="radio"'));
        $this->assertSame(1, substr_count($form->getContent(), '<textarea'));
        $this->assertSame(0, substr_count($form->getContent(), 'type="text"'));
        $this->assertSame(0, substr_count($form->getContent(), 'type="date"'));

        $this->post(self::PORTAL.'/evaluation', $this->answers(5, '  Sangat membantu.  '))
            ->assertRedirect(self::PORTAL.'/quiz')->assertSessionHas('success');

        $evaluation = TrainingEvaluation::sole();
        $this->assertSame($this->training->id, $evaluation->training_id);
        $this->assertSame($this->participant->id, $evaluation->training_participant_id);
        $this->assertSame($this->staff->id, $evaluation->user_id);
        $this->assertCount(10, $evaluation->answers);
        foreach ($this->scaleQuestions() as $question) {
            $this->assertSame(5, $evaluation->answerTo($question));
        }
        $this->assertSame('Sangat membantu.', $evaluation->answerTo($this->commentQuestion()));
        $this->assertSame(5.0, $evaluation->average($this->training->evaluationQuestions));

        // Setelah evaluasi terisi, posttest terbuka dan nilainya tersimpan seperti biasa.
        $this->get(self::PORTAL.'/quiz')->assertOk()->assertSee('Batas toleransi terlambat?');
        $this->post(self::PORTAL.'/quiz', ['answers' => [$this->question->id => 'a']])->assertRedirect(self::PORTAL.'/result');
        $this->assertSame(100.0, TrainingQuizResult::sole()->score);

        $this->get(self::PORTAL)->assertSee('Terima kasih, evaluasi Anda sudah tersimpan')->assertDontSee('Isi Form Evaluasi');
    }

    public function test_evaluation_is_sent_only_once(): void
    {
        $this->training->update(['is_quiz_active' => true, 'is_evaluation_active' => true]);

        $this->post(self::PORTAL.'/evaluation', $this->answers(5))->assertRedirect(self::PORTAL.'/quiz');
        $this->post(self::PORTAL.'/evaluation', $this->answers(1))->assertRedirect(self::PORTAL)->assertSessionHas('warning');
        $this->get(self::PORTAL.'/evaluation')->assertRedirect(self::PORTAL);

        $this->assertSame(5.0, TrainingEvaluation::sole()->average($this->training->evaluationQuestions));
    }

    public function test_required_answers_are_enforced_per_question_type(): void
    {
        $this->training->update(['is_quiz_active' => true, 'is_evaluation_active' => true]);
        $first = $this->scaleQuestions()->first();

        $missing = $this->answers();
        unset($missing['answers'][$first->id]);
        $this->from(self::PORTAL.'/evaluation')->post(self::PORTAL.'/evaluation', $missing)->assertSessionHasErrors(['answers.'.$first->id]);

        $outOfRange = $this->answers();
        $outOfRange['answers'][$first->id] = 6;
        $this->from(self::PORTAL.'/evaluation')->post(self::PORTAL.'/evaluation', $outOfRange)->assertSessionHasErrors(['answers.'.$first->id]);

        $this->assertSame(0, TrainingEvaluation::count());

        // Pertanyaan opsional boleh kosong; jawaban untuk id di luar pertanyaan pelatihan ini diabaikan.
        $answers = $this->answers(3, '');
        $answers['answers'][999999] = 5;
        $this->post(self::PORTAL.'/evaluation', $answers)->assertSessionHasNoErrors();
        $evaluation = TrainingEvaluation::sole();
        $this->assertNull($evaluation->answerTo($this->commentQuestion()));
        $this->assertArrayNotHasKey(999999, $evaluation->answers);
        $this->assertCount(9, $evaluation->answers);
    }

    public function test_trainer_manages_the_questions_of_one_training(): void
    {
        $manage = route('training.evaluation.index', $this->training);
        $this->actingAs($this->admin)->get($manage)->assertOk()
            ->assertSee('Pertanyaan Form Evaluasi')->assertSee('Sesi pelatihan terorganisir dengan baik')->assertSee('Tambah Pertanyaan');
        $this->actingAs($this->admin)->get(route('training.show', $this->training))->assertSee('Kelola pertanyaan (10)');

        // Tambah pertanyaan pilihan dan teks wajib khusus pelatihan ini.
        $this->actingAs($this->admin)->post(route('training.evaluation.store', $this->training), [
            'section' => 'Kebutuhan Manajemen', 'type' => 'choice', 'question' => 'Apakah perlu pelatihan lanjutan?',
            'options' => "Ya\n\nTidak\nYa\n  Ragu-ragu  ", 'is_required' => '1',
        ])->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->actingAs($this->admin)->post(route('training.evaluation.store', $this->training), [
            'type' => 'text', 'question' => 'Topik apa yang ingin dibahas berikutnya?', 'is_required' => '1',
            'low_label' => 'diabaikan', 'options' => 'diabaikan',
        ])->assertSessionHasNoErrors();

        $choice = $this->training->evaluationQuestions()->where('type', 'choice')->sole();
        $this->assertSame(['Ya', 'Tidak', 'Ragu-ragu'], $choice->options);
        $this->assertSame(11, $choice->position);
        $topic = $this->training->evaluationQuestions()->where('question', 'like', 'Topik%')->sole();
        $this->assertNull($topic->low_label);
        $this->assertNull($topic->options);
        $this->assertTrue($topic->is_required);

        // Pilihan butuh minimal dua opsi.
        $this->actingAs($this->admin)->post(route('training.evaluation.store', $this->training), [
            'type' => 'choice', 'question' => 'Kurang opsi', 'options' => 'Satu saja',
        ])->assertSessionHasErrors(['options']);

        // Ubah, geser, dan hapus.
        $first = $this->scaleQuestions()->first();
        $this->actingAs($this->admin)->put(route('training.evaluation.update', [$this->training, $first]), [
            'section' => 'Pemateri', 'type' => 'scale', 'question' => 'Pemateri menjelaskan sanksi dengan jelas',
            'low_label' => 'Tidak jelas', 'high_label' => 'Sangat jelas', 'is_required' => '0',
        ])->assertSessionHasNoErrors();
        $first->refresh();
        $this->assertSame('Pemateri menjelaskan sanksi dengan jelas', $first->question);
        $this->assertSame('Sangat jelas', $first->high_label);
        $this->assertFalse($first->is_required);

        $this->actingAs($this->admin)->post(route('training.evaluation.move', [$this->training, $topic]), ['direction' => 'up']);
        $this->assertSame([$topic->id, $choice->id], $this->training->evaluationQuestions()->pluck('id')->slice(-2)->values()->all());
        // Sudah di ujung: tidak bergeser lagi.
        $this->actingAs($this->admin)->post(route('training.evaluation.move', [$this->training, $choice]), ['direction' => 'down']);
        $this->assertSame($choice->id, $this->training->evaluationQuestions()->pluck('id')->last());

        $second = $this->scaleQuestions()->get(1);
        $this->actingAs($this->admin)->delete(route('training.evaluation.destroy', [$this->training, $second]))->assertSessionHas('success');
        $this->assertSame(11, $this->training->evaluationQuestions()->count());

        // Peserta melihat form sesuai pertanyaan pelatihan ini dan pertanyaan wajib yang baru ikut divalidasi.
        $this->training->update(['is_quiz_active' => true, 'is_evaluation_active' => true]);
        $this->get(self::PORTAL.'/evaluation')->assertOk()
            ->assertSee('Pemateri menjelaskan sanksi dengan jelas')->assertSee('Sangat jelas')
            ->assertSee('Kebutuhan Manajemen')->assertSee('Apakah perlu pelatihan lanjutan?')->assertSee('Ragu-ragu')
            ->assertSee('Topik apa yang ingin dibahas berikutnya?')
            ->assertDontSee($second->question);

        $answers = $this->answers(4, 'Penanganan komplain');
        $answers['answers'][$choice->id] = 'Mungkin';
        $this->from(self::PORTAL.'/evaluation')->post(self::PORTAL.'/evaluation', $answers)->assertSessionHasErrors(['answers.'.$choice->id]);
        unset($answers['answers'][$topic->id]);
        $answers['answers'][$choice->id] = 'Tidak';
        $this->from(self::PORTAL.'/evaluation')->post(self::PORTAL.'/evaluation', $answers)->assertSessionHasErrors(['answers.'.$topic->id]);

        $answers['answers'][$topic->id] = 'Penanganan komplain';
        $this->post(self::PORTAL.'/evaluation', $answers)->assertSessionHasNoErrors();
        $evaluation = TrainingEvaluation::sole();
        $this->assertSame('Tidak', $evaluation->answerTo($choice));
        $this->assertSame('Penanganan komplain', $evaluation->answerTo($topic));

        // Hasil di halaman detail mengikuti tipe pertanyaan.
        $this->actingAs($this->admin)->get(route('training.show', $this->training))->assertOk()
            ->assertSeeInOrder(['Penanganan komplain', 'Apakah perlu pelatihan lanjutan?', '0 &middot; 0%', '1 &middot; 100%'], false);

        // Setelah ada jawaban, tipe tidak bisa diganti; teksnya masih bisa.
        $this->actingAs($this->admin)->put(route('training.evaluation.update', [$this->training, $topic]), [
            'type' => 'scale', 'question' => $topic->question,
        ])->assertSessionHasErrors(['type']);
        $this->actingAs($this->admin)->put(route('training.evaluation.update', [$this->training, $topic]), [
            'type' => 'text', 'question' => 'Topik lanjutan yang diinginkan?', 'is_required' => '1',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Topik lanjutan yang diinginkan?', $topic->fresh()->question);
    }

    public function test_questions_belong_to_their_own_training_and_need_permission(): void
    {
        $other = Training::create([
            'title' => 'Pelatihan Lain', 'trainer_id' => $this->admin->id, 'training_date' => now()->toDateString(),
            'start_time' => '09:00', 'end_time' => '10:00', 'zoom_link' => 'https://zoom.us/test', 'has_evaluation' => true,
        ]);
        $foreign = $other->evaluationQuestions()->first();

        // Pertanyaan pelatihan lain tidak bisa diubah lewat alamat pelatihan ini.
        $this->actingAs($this->admin)->put(route('training.evaluation.update', [$this->training, $foreign]), [
            'type' => 'scale', 'question' => 'Dibajak',
        ])->assertNotFound();
        $this->actingAs($this->admin)->delete(route('training.evaluation.destroy', [$this->training, $foreign]))->assertNotFound();

        // Peserta biasa tidak boleh mengelola pertanyaan.
        $this->actingAs($this->staff)->get(route('training.evaluation.index', $this->training))->assertRedirect();
        $this->actingAs($this->staff)->from('/my-trainings')->post(route('training.evaluation.store', $this->training), [
            'type' => 'text', 'question' => 'Tidak boleh',
        ])->assertSessionHas('error');
        $this->assertSame(10, $this->training->evaluationQuestions()->count());
        $this->assertSame('Instruksi yang diberikan pemateri jelas dan mudah dipahami', $foreign->fresh()->question);
    }

    public function test_form_without_questions_does_not_hold_back_the_posttest(): void
    {
        $this->training->update(['is_quiz_active' => true, 'is_evaluation_active' => true]);
        $this->training->evaluationQuestions()->delete();

        $this->get(self::PORTAL)->assertOk()->assertDontSee('Evaluasi Pelatihan');
        $this->get(self::PORTAL.'/quiz')->assertOk();
        $this->get(self::PORTAL.'/status')->assertJson(['has_evaluation' => false, 'is_evaluation_open' => false]);

        $manage = route('training.evaluation.index', $this->training);
        $this->actingAs($this->admin)->get($manage)->assertOk()->assertSee('Belum ada pertanyaan')->assertSee('Pakai template bawaan');
        $this->actingAs($this->admin)->get(route('training.show', $this->training))->assertSee('Belum ada pertanyaan, jadi form belum bisa dibuka');

        $this->actingAs($this->admin)->post(route('training.evaluation.defaults', $this->training))->assertSessionHas('success');
        $this->assertSame(10, $this->training->evaluationQuestions()->count());
        // Template hanya untuk form kosong.
        $this->actingAs($this->admin)->post(route('training.evaluation.defaults', $this->training))->assertSessionHas('error');
        $this->assertSame(10, $this->training->evaluationQuestions()->count());

        $this->get(self::PORTAL.'/quiz')->assertRedirect(self::PORTAL.'/evaluation');
    }

    public function test_posttest_opens_directly_when_the_evaluation_is_off(): void
    {
        $this->training->update(['has_evaluation' => false, 'is_quiz_active' => true]);

        $this->get(self::PORTAL)->assertOk()->assertDontSee('Evaluasi Pelatihan')
            ->assertSee('href="'.self::PORTAL.'/quiz"', false);
        $this->get(self::PORTAL.'/quiz')->assertOk();
        $this->get(self::PORTAL.'/evaluation')->assertRedirect(self::PORTAL)->assertSessionHas('warning');
        $this->post(self::PORTAL.'/evaluation', $this->answers())->assertNotFound();
        $this->assertSame(0, TrainingEvaluation::count());
    }

    public function test_training_without_posttest_also_waits_for_the_trainer_to_open_the_evaluation(): void
    {
        $this->training->update(['has_posttest' => false, 'status' => TrainingStatus::Ongoing]);

        // Sesi berlangsung saja belum membuka form.
        $this->get(self::PORTAL)->assertSee('Form evaluasi belum dibuka oleh pemateri.');
        $this->get(self::PORTAL.'/evaluation')->assertRedirect(self::PORTAL);

        $this->openEvaluation()->assertSessionHas('success');

        $this->get(self::PORTAL.'/evaluation')->assertOk()->assertSee('Kirim Evaluasi');
        $this->post(self::PORTAL.'/evaluation', $this->answers())->assertRedirect(self::PORTAL)->assertSessionHas('success');
        $this->assertSame(1, TrainingEvaluation::count());
    }

    public function test_detail_page_and_export_show_the_answers(): void
    {
        $this->training->update(['is_quiz_active' => true, 'is_evaluation_active' => true]);
        $this->post(self::PORTAL.'/evaluation', $this->answers(4, 'Durasinya kurang lama.'));

        $second = TrainingParticipant::create([
            'training_id' => $this->training->id, 'user_id' => User::factory()->staff()->create()->id,
            'token' => 'token_evaluasi_2', 'attendance_status' => 'hadir',
        ]);
        $this->post('/training/portal/token_evaluasi_2/evaluation', $this->answers(5, ''));

        $this->actingAs($this->admin)->get(route('training.show', $this->training))->assertOk()
            ->assertSee('Hasil Evaluasi Peserta')->assertSee('2 dari 2 mengisi')->assertSee('4.50')
            ->assertSee('Durasinya kurang lama.')->assertSee('Evaluasi: 4.0 / 5');

        $training = Training::with([
            'evaluationQuestions', 'participants.evaluation',
            'participants.user.divisi', 'participants.user.position', 'participants.user.area', 'participants.user.roles',
        ])->findOrFail($this->training->id);
        $sheet = new TrainingEvaluationSheet($training);
        $rows = $sheet->array();

        $this->assertCount(2, $rows);
        $this->assertCount(count($sheet->headings()), $rows[0]);
        $this->assertContains('Sesi pelatihan terorganisir dengan baik', $sheet->headings());
        $this->assertSame('Sari Peserta', $rows[0][2]);
        $this->assertSame('Ketentuan Sanksi Administrasi', $rows[0][8]);
        // Kolom terakhir rata-rata skala, sebelumnya jawaban pertanyaan masukan.
        $this->assertSame('Durasinya kurang lama.', $rows[0][array_key_last($rows[0]) - 1]);
        $this->assertSame('-', $rows[1][array_key_last($rows[1]) - 1]);
        $this->assertSame(5.0, $rows[1][array_key_last($rows[1])]);

        // Rekap pelatihan mendapat sheet Evaluasi; jawaban tetap ikut walau form lalu dimatikan. Export tidak boleh
        // memuat relasi satu per satu per peserta: pelanggaran lazy loading menggagalkan export di test ini.
        Model::handleLazyLoadingViolationUsing(fn ($model, string $relation) => throw new LogicException("Lazy loaded [{$relation}]"));
        $this->training->update(['has_evaluation' => false]);
        Storage::fake('local');
        (new GenerateExportFile($this->admin->id, GenerateExportFile::TRAINING, $this->training->id))->handle();

        $file = collect(Storage::disk('local')->allFiles(GenerateExportFile::DIRECTORY))->sole();
        $this->assertStringContainsString('rekap_pelatihan_ketentuan-sanksi-administrasi_', $file);
        $workbook = IOFactory::load(Storage::disk('local')->path($file));
        $this->assertSame(['Rekap Pelatihan', 'Evaluasi'], $workbook->getSheetNames());
        $this->assertSame('Sari Peserta', $workbook->getSheetByName('Evaluasi')->getCell('C2')->getValue());

        // Pelatihan tanpa evaluasi tetap satu sheet seperti sebelumnya.
        TrainingEvaluation::query()->delete();
        Storage::fake('local');
        (new GenerateExportFile($this->admin->id, GenerateExportFile::TRAINING, $this->training->id))->handle();
        $plain = collect(Storage::disk('local')->allFiles(GenerateExportFile::DIRECTORY))->sole();
        $this->assertSame(['Rekap Pelatihan'], IOFactory::load(Storage::disk('local')->path($plain))->getSheetNames());
    }

    public function test_text_answers_stay_text_in_the_export(): void
    {
        $this->training->update(['is_quiz_active' => true, 'is_evaluation_active' => true]);
        $comment = $this->commentQuestion();

        // Jawaban berawalan "=" dulu dianggap rumus: rumus rusak menggagalkan seluruh export, rumus sah dijalankan Excel.
        $answers = $this->answers(5);
        $answers['answers'][$comment->id] = '=SUM(';
        $this->post(self::PORTAL.'/evaluation', $answers)->assertSessionHasNoErrors();

        $second = TrainingParticipant::create([
            'training_id' => $this->training->id, 'user_id' => User::factory()->staff()->create()->id,
            'token' => 'token_evaluasi_2', 'attendance_status' => 'hadir',
        ]);
        $answers = $this->answers(4);
        $answers['answers'][$comment->id] = '+6281234567890';
        $this->post('/training/portal/token_evaluasi_2/evaluation', $answers)->assertSessionHasNoErrors();

        Storage::fake('local');
        (new GenerateExportFile($this->admin->id, GenerateExportFile::TRAINING, $this->training->id))->handle();

        $file = collect(Storage::disk('local')->allFiles(GenerateExportFile::DIRECTORY))->sole();
        $sheet = IOFactory::load(Storage::disk('local')->path($file))->getSheetByName('Evaluasi');
        $column = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($sheet->getHighestColumn()) - 1);

        $this->assertSame('=SUM(', $sheet->getCell($column.'2')->getValue());
        $this->assertSame(DataType::TYPE_STRING, $sheet->getCell($column.'2')->getDataType());
        $this->assertSame('+6281234567890', $sheet->getCell($column.'3')->getValue());
        $this->assertSame(DataType::TYPE_STRING, $sheet->getCell($column.'3')->getDataType());
        // Nilai skala tetap angka.
        $this->assertSame(DataType::TYPE_NUMERIC, $sheet->getCell('K2')->getDataType());

        // Pengikat nilai kembali ke bawaan, jadi export lain di proses yang sama tidak ikut berubah.
        $this->assertNotInstanceOf(TrainingEvaluationSheet::class, Cell::getValueBinder());
    }

    public function test_choice_options_keep_zero_and_answers_to_removed_options_stay_counted(): void
    {
        $this->actingAs($this->admin)->post(route('training.evaluation.store', $this->training), [
            'type' => 'choice', 'question' => 'Berapa kali terlambat bulan ini?', 'options' => "0\n1\n2 atau lebih", 'is_required' => '1',
        ])->assertSessionHasNoErrors();
        $choice = $this->training->evaluationQuestions()->where('type', 'choice')->sole();
        $this->assertSame(['0', '1', '2 atau lebih'], $choice->options);

        $this->training->update(['is_quiz_active' => true, 'is_evaluation_active' => true]);
        $answers = $this->answers();
        $answers['answers'][$choice->id] = '0';
        $this->post(self::PORTAL.'/evaluation', $answers)->assertSessionHasNoErrors();
        $this->assertSame('0', TrainingEvaluation::sole()->answerTo($choice));

        // Opsi "0" dihapus setelah ada yang memilihnya: jawabannya tetap terhitung sebagai opsi lama.
        $this->actingAs($this->admin)->put(route('training.evaluation.update', [$this->training, $choice]), [
            'type' => 'choice', 'question' => $choice->question, 'options' => "1\n2 atau lebih", 'is_required' => '1',
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->admin)->get(route('training.show', $this->training))->assertOk()
            ->assertSeeInOrder(['Berapa kali terlambat bulan ini?', '0 &middot; 0%', '0 &middot; 0%', '0 (opsi lama)', '1 &middot; 100%'], false);
    }

    public function test_question_form_reopens_with_typed_values_after_a_validation_error(): void
    {
        $manage = route('training.evaluation.index', $this->training);

        $this->actingAs($this->admin)->get($manage)->assertOk()->assertSee('const failed = null;', false);

        // Kiriman gagal diikuti sampai halaman kelola tampil lagi, seperti di browser.
        $this->actingAs($this->admin)->followingRedirects()->from($manage)->post(route('training.evaluation.store', $this->training), [
            'type' => 'choice', 'question' => 'Pilihan yang kurang opsi', 'options' => 'Satu saja', 'section' => 'Uji', 'is_required' => '1',
        ])->assertOk()->assertSee('Tulis 2 sampai 20 pilihan')
            ->assertSee('"question":"Pilihan yang kurang opsi"', false)->assertSee('"options":"Satu saja"', false)->assertSee('"action":null', false);
        $this->assertSame(10, $this->training->evaluationQuestions()->count());

        // Gagal saat mengubah: form kembali mengarah ke pertanyaan yang sama.
        $first = $this->scaleQuestions()->first();
        $this->actingAs($this->admin)->followingRedirects()->from($manage)->put(route('training.evaluation.update', [$this->training, $first]), [
            'type' => 'scale', 'question' => '', 'editing_id' => $first->id,
        ])->assertOk()->assertSee('Tulis pertanyaannya.')
            ->assertSee('"id":'.$first->id, false)
            ->assertSee(json_encode(route('training.evaluation.update', [$this->training, $first]), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT), false);
    }

    public function test_evaluation_can_be_opened_even_when_the_posttest_has_no_questions(): void
    {
        $this->question->delete();

        $this->openEvaluation()->assertSessionHas('success');
        $this->actingAs($this->admin)->get(route('training.show', $this->training))->assertOk()->assertDontSee('Posttest belum punya soal');
        $this->get(self::PORTAL.'/evaluation')->assertOk();
    }

    public function test_pasted_or_unusual_options_can_still_be_chosen(): void
    {
        // Spasi tak terlihat dari hasil tempel, opsi angka yang mirip, dan opsi berakhiran garis miring terbalik.
        $this->actingAs($this->admin)->post(route('training.evaluation.store', $this->training), [
            'type' => 'choice', 'question' => 'Pilih satu', 'is_required' => '1',
            'options' => "Ya\u{00A0}\n1\n01\n1\nFolder C:\\\n\"Kutip\", koma",
        ])->assertSessionHasNoErrors();
        $choice = $this->training->evaluationQuestions()->where('type', 'choice')->sole();
        $this->assertSame(['Ya', '1', '01', 'Folder C:\\', '"Kutip", koma'], $choice->options);

        $this->training->update(['is_quiz_active' => true, 'is_evaluation_active' => true]);
        $portals = [];
        foreach ($choice->options as $index => $option) {
            TrainingParticipant::create([
                'training_id' => $this->training->id, 'user_id' => User::factory()->staff()->create()->id,
                'token' => 'token_opsi_'.$index, 'attendance_status' => 'hadir',
            ]);
            $answers = $this->answers();
            $answers['answers'][$choice->id] = $option;
            $this->post('/training/portal/token_opsi_'.$index.'/evaluation', $answers)->assertSessionHasNoErrors();
            $portals[] = $option;
        }

        $this->assertSame($portals, TrainingEvaluation::orderBy('id')->get()->map->answerTo($choice)->all());

        // Nilai di luar daftar tetap ditolak.
        $answers = $this->answers();
        $answers['answers'][$choice->id] = '1.0';
        $this->from(self::PORTAL.'/evaluation')->post(self::PORTAL.'/evaluation', $answers)->assertSessionHasErrors(['answers.'.$choice->id]);
    }

    public function test_text_limit_counts_a_line_break_once_like_the_browser(): void
    {
        $this->training->update(['is_quiz_active' => true, 'is_evaluation_active' => true]);
        $comment = $this->commentQuestion();

        // 990 huruf + 10 baris baru = 1000 karakter di browser, tetapi terkirim sebagai 1010 karakter (CR+LF).
        $text = implode("\r\n", array_fill(0, 11, str_repeat('a', 90)));
        $this->assertSame(1010, strlen($text));

        $tooLong = $this->answers();
        $tooLong['answers'][$comment->id] = $text.'b';
        $this->from(self::PORTAL.'/evaluation')->post(self::PORTAL.'/evaluation', $tooLong)->assertSessionHasErrors(['answers.'.$comment->id]);

        $answers = $this->answers();
        $answers['answers'][$comment->id] = $text;
        $this->post(self::PORTAL.'/evaluation', $answers)->assertSessionHasNoErrors();
        $this->assertSame(str_replace("\r\n", "\n", $text), TrainingEvaluation::sole()->answerTo($comment));
    }

    public function test_malformed_answers_are_rejected_without_breaking_the_form(): void
    {
        $this->training->update(['is_quiz_active' => true, 'is_evaluation_active' => true]);
        $first = $this->scaleQuestions()->first();
        $comment = $this->commentQuestion();

        $answers = $this->answers();
        $answers['answers'][$first->id] = ['x'];
        $answers['answers'][$comment->id] = ['y'];

        // Kiriman ditolak dan form tampil kembali dengan normal, bukan halaman error.
        $this->followingRedirects()->from(self::PORTAL.'/evaluation')->post(self::PORTAL.'/evaluation', $answers)
            ->assertOk()->assertSee('Form Evaluasi Pelatihan')->assertSee('Perhatian:');
        $this->assertSame(0, TrainingEvaluation::count());
    }
}
