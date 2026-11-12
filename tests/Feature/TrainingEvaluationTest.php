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
 * Form evaluasi pelatihan: bisa dinyalakan/dimatikan per pelatihan, diisi peserta tepat sebelum posttest, dan tidak
 * menanyakan data yang sudah ada di sistem (nama, jabatan, divisi, tanggal, materi).
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
            ->assertSee('Form Evaluasi')->assertSee('Diisi peserta sebelum posttest')->assertSee('Nonaktifkan');

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

    public function test_evaluation_waits_until_the_posttest_is_opened(): void
    {
        $this->get(self::PORTAL)->assertOk()->assertSee('Evaluasi Pelatihan')->assertSee('Form evaluasi dibuka bersamaan dengan kuis');
        $this->get(self::PORTAL.'/status')->assertJson(['has_evaluation' => true, 'is_evaluation_open' => false]);
        $this->get(self::PORTAL.'/evaluation')->assertRedirect(self::PORTAL)->assertSessionHas('warning');
        $this->post(self::PORTAL.'/evaluation', $this->answers())->assertRedirect(self::PORTAL)->assertSessionHas('warning');
        $this->assertSame(0, TrainingEvaluation::count());

        $this->training->update(['is_quiz_active' => true]);

        $this->get(self::PORTAL.'/status')->assertJson(['is_evaluation_open' => true]);
        $this->get(self::PORTAL)->assertSee('Isi Form Evaluasi')->assertSee('Isi Evaluasi, lalu Kerjakan Kuis')
            ->assertDontSee('href="'.self::PORTAL.'/quiz"', false);
    }

    public function test_participant_fills_the_evaluation_before_the_posttest(): void
    {
        $this->training->update(['is_quiz_active' => true]);

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
        $this->training->update(['is_quiz_active' => true]);

        $this->post(self::PORTAL.'/evaluation', $this->answers(5))->assertRedirect(self::PORTAL.'/quiz');
        $this->post(self::PORTAL.'/evaluation', $this->answers(1))->assertRedirect(self::PORTAL)->assertSessionHas('warning');
        $this->get(self::PORTAL.'/evaluation')->assertRedirect(self::PORTAL);

        $this->assertSame(5.0, TrainingEvaluation::sole()->average($this->training->evaluationQuestions));
    }

    public function test_required_answers_are_enforced_per_question_type(): void
    {
        $this->training->update(['is_quiz_active' => true]);
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
        $this->training->update(['is_quiz_active' => true]);
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
        $this->training->update(['is_quiz_active' => true]);
        $this->training->evaluationQuestions()->delete();

        $this->get(self::PORTAL)->assertOk()->assertDontSee('Evaluasi Pelatihan');
        $this->get(self::PORTAL.'/quiz')->assertOk();
        $this->get(self::PORTAL.'/status')->assertJson(['has_evaluation' => false, 'is_evaluation_open' => false]);

        $manage = route('training.evaluation.index', $this->training);
        $this->actingAs($this->admin)->get($manage)->assertOk()->assertSee('Belum ada pertanyaan')->assertSee('Pakai template bawaan');
        $this->actingAs($this->admin)->get(route('training.show', $this->training))->assertSee('Belum ada pertanyaan, jadi form belum tampil ke peserta');

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

    public function test_training_without_posttest_opens_the_evaluation_while_the_session_runs(): void
    {
        $this->training->update(['has_posttest' => false]);

        $this->get(self::PORTAL)->assertSee('Form evaluasi dibuka saat sesi berlangsung');
        $this->get(self::PORTAL.'/evaluation')->assertRedirect(self::PORTAL);

        $this->training->update(['status' => TrainingStatus::Ongoing]);

        $this->get(self::PORTAL.'/evaluation')->assertOk()->assertSee('Kirim Evaluasi');
        $this->post(self::PORTAL.'/evaluation', $this->answers())->assertRedirect(self::PORTAL)->assertSessionHas('success');
        $this->assertSame(1, TrainingEvaluation::count());
    }

    public function test_detail_page_and_export_show_the_answers(): void
    {
        $this->training->update(['is_quiz_active' => true]);
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
        $this->training->update(['is_quiz_active' => true]);
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

        $this->training->update(['is_quiz_active' => true]);
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

    public function test_session_controls_warn_when_the_posttest_has_no_questions(): void
    {
        $this->actingAs($this->admin)->get(route('training.show', $this->training))->assertOk()->assertDontSee('Posttest belum punya soal');

        $this->question->delete();
        $this->actingAs($this->admin)->get(route('training.show', $this->training))->assertOk()
            ->assertSee('Posttest belum punya soal, jadi evaluasi belum bisa dibuka');

        // Tanpa posttest, evaluasi mengikuti status sesi, jadi peringatan itu tidak relevan.
        $this->training->update(['has_posttest' => false]);
        $this->actingAs($this->admin)->get(route('training.show', $this->training))->assertOk()->assertDontSee('Posttest belum punya soal');
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

        $this->training->update(['is_quiz_active' => true]);
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
        $this->training->update(['is_quiz_active' => true]);
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
        $this->training->update(['is_quiz_active' => true]);
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
