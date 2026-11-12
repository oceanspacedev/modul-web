<?php

namespace Tests\Feature;

use App\Enums\TrainingStatus;
use App\Exports\TrainingEvaluationSheet;
use App\Jobs\GenerateExportFile;
use App\Models\Training;
use App\Models\TrainingEvaluation;
use App\Models\TrainingParticipant;
use App\Models\TrainingQuestion;
use App\Models\TrainingQuizResult;
use App\Models\User;
use App\Support\TrainingEvaluationForm;
use Illuminate\Support\Facades\Storage;
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
     * @return array{ratings: array<string, int>, comment: string}
     */
    private function answers(int $value = 4, string $comment = 'Materinya jelas.'): array
    {
        return ['ratings' => array_fill_keys(array_keys(TrainingEvaluationForm::questions()), $value), 'comment' => $comment];
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

        $this->actingAs($this->admin)->put(route('training.update', $training), [...$payload, 'status' => 'scheduled', 'has_evaluation' => '0'])
            ->assertSessionHasNoErrors();
        $this->assertFalse($training->fresh()->has_evaluation);

        // Bawaan: mati.
        $this->actingAs($this->admin)->post('/training', [...$payload, 'title' => 'Tanpa Evaluasi'])->assertSessionHasNoErrors();
        $this->assertFalse(Training::where('title', 'Tanpa Evaluasi')->sole()->has_evaluation);
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
        $this->assertSame(count(TrainingEvaluationForm::questions()) * 5, substr_count($form->getContent(), 'type="radio"'));
        $this->assertSame(0, substr_count($form->getContent(), 'type="text"'));
        $this->assertSame(0, substr_count($form->getContent(), 'type="date"'));

        $this->post(self::PORTAL.'/evaluation', $this->answers(5, '  Sangat membantu.  '))
            ->assertRedirect(self::PORTAL.'/quiz')->assertSessionHas('success');

        $evaluation = TrainingEvaluation::sole();
        $this->assertSame($this->training->id, $evaluation->training_id);
        $this->assertSame($this->participant->id, $evaluation->training_participant_id);
        $this->assertSame($this->staff->id, $evaluation->user_id);
        $this->assertSame(array_fill_keys(array_keys(TrainingEvaluationForm::questions()), 5), $evaluation->ratings);
        $this->assertSame('Sangat membantu.', $evaluation->comment);
        $this->assertSame(5.0, $evaluation->average());

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

        $this->assertSame(5.0, TrainingEvaluation::sole()->average());
    }

    public function test_every_rating_is_required_and_within_the_scale(): void
    {
        $this->training->update(['is_quiz_active' => true]);

        $missing = $this->answers();
        unset($missing['ratings']['overall']);
        $this->from(self::PORTAL.'/evaluation')->post(self::PORTAL.'/evaluation', $missing)->assertSessionHasErrors(['ratings.overall']);

        $outOfRange = $this->answers();
        $outOfRange['ratings']['trainer_clarity'] = 6;
        $this->from(self::PORTAL.'/evaluation')->post(self::PORTAL.'/evaluation', $outOfRange)->assertSessionHasErrors(['ratings.trainer_clarity']);

        $this->assertSame(0, TrainingEvaluation::count());

        // Masukan boleh kosong; field di luar daftar pertanyaan diabaikan.
        $answers = $this->answers(3, '');
        $answers['ratings']['bukan_pertanyaan'] = 5;
        $this->post(self::PORTAL.'/evaluation', $answers)->assertSessionHasNoErrors();
        $evaluation = TrainingEvaluation::sole();
        $this->assertNull($evaluation->comment);
        $this->assertArrayNotHasKey('bukan_pertanyaan', $evaluation->ratings);
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

        $training = Training::with(['participants.evaluation', 'participants.user'])->findOrFail($this->training->id);
        $sheet = new TrainingEvaluationSheet($training);
        $rows = $sheet->array();

        $this->assertCount(2, $rows);
        $this->assertCount(count($sheet->headings()), $rows[0]);
        $this->assertSame('Sari Peserta', $rows[0][2]);
        $this->assertSame('Ketentuan Sanksi Administrasi', $rows[0][8]);
        $this->assertSame('Durasinya kurang lama.', $rows[0][array_key_last($rows[0])]);
        $this->assertSame(5.0, $rows[1][array_key_last($rows[1]) - 1]);

        // Rekap pelatihan mendapat sheet Evaluasi; jawaban tetap ikut walau form lalu dimatikan.
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
}
