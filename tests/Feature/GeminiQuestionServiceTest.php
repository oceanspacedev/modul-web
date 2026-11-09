<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Services\GeminiQuestionService;
use Exception;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * "Buat Soal AI" membaca file dokumen dari disk upload (S3 di server), bukan dari path lokal.
 */
class GeminiQuestionServiceTest extends TestCase
{
    private function fakeGemini(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => json_encode(['questions' => [[
                    'question' => 'Apa langkah pertama SOP?',
                    'option_a' => 'Cuci tangan',
                    'option_b' => 'Makan',
                    'option_c' => 'Tidur',
                    'option_d' => 'Pulang',
                    'correct_answer' => 'a',
                ]]])]]]]],
            ]),
        ]);
    }

    public function test_document_on_the_default_upload_disk_is_sent_to_gemini(): void
    {
        config(['filesystems.default' => 's3']);
        Storage::fake('s3')->put('dokumen/sop-cuci-tangan.txt', 'Langkah 1: cuci tangan dengan sabun.');
        $this->fakeGemini();
        $tempFilesBefore = glob(sys_get_temp_dir().'/gemini-doc-*');

        $document = new Document(['name' => 'SOP Cuci Tangan']);
        $document->path = 'sop-cuci-tangan.txt';

        $questions = (new GeminiQuestionService('test-key'))->generateQuestions([$document], 1);

        $this->assertSame('Apa langkah pertama SOP?', $questions[0]['question']);
        Http::assertSent(fn (Request $request) => str_contains(
            $request['contents'][0]['parts'][0]['text'] ?? '',
            'Langkah 1: cuci tangan dengan sabun.'
        ));
        // Salinan sementara untuk Gemini dihapus lagi.
        $this->assertSame($tempFilesBefore, glob(sys_get_temp_dir().'/gemini-doc-*'));
    }

    public function test_missing_document_file_still_reports_a_clear_error(): void
    {
        config(['filesystems.default' => 's3']);
        Storage::fake('s3');
        Storage::fake('public');
        $this->fakeGemini();

        $document = new Document(['name' => 'SOP Hilang']);
        $document->path = 'tidak-ada.pdf';

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('File fisik dari dokumen yang dipilih tidak ditemukan pada storage server.');

        (new GeminiQuestionService('test-key'))->generateQuestions([$document], 1);
    }

    private const CURRENT_QUESTION = [
        'type' => 'multiple_choice',
        'question' => 'Apa langkah pertama SOP?',
        'option_a' => 'Cuci tangan', 'option_b' => 'Makan', 'option_c' => 'Tidur', 'option_d' => 'Pulang', 'option_e' => 'Lari',
        'correct_answer' => 'a',
        'explanation' => 'Lihat SOP.',
    ];

    private function fakeRevision(array $revised): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => '```json'.json_encode($revised).'```']]]]],
            ]),
        ]);
    }

    public function test_revising_only_the_question_keeps_the_answers(): void
    {
        $this->fakeRevision([
            'question' => 'Langkah apa yang dilakukan paling awal menurut SOP?',
            'option_a' => 'Diubah', 'option_b' => 'Diubah', 'correct_answer' => 'c', 'explanation' => 'Pembahasan baru.',
        ]);

        $revised = (new GeminiQuestionService('test-key'))->reviseQuestion(self::CURRENT_QUESTION, 'question', 'perjelas kalimatnya')['question'];

        $this->assertSame('Langkah apa yang dilakukan paling awal menurut SOP?', $revised['question']);
        $this->assertSame('Cuci tangan', $revised['option_a']);
        $this->assertSame('a', $revised['correct_answer']);
        $this->assertSame('Pembahasan baru.', $revised['explanation']);
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => str_contains($request['contents'][0]['parts'][0]['text'], 'Apa langkah pertama SOP?')
            && str_contains($request['contents'][0]['parts'][0]['text'], 'perjelas kalimatnya'));
    }

    public function test_revising_only_the_answers_keeps_the_question(): void
    {
        $this->fakeRevision([
            'question' => 'Pertanyaan yang tidak diminta diubah',
            'option_a' => 'Pulang', 'option_b' => 'Cuci tangan', 'option_c' => 'Tidur', 'option_d' => 'Makan', 'option_e' => 'Lari',
            'correct_answer' => 'B', 'explanation' => 'Cuci tangan dulu.',
        ]);

        $revised = (new GeminiQuestionService('test-key'))->reviseQuestion(self::CURRENT_QUESTION, 'answers')['question'];

        $this->assertSame('Apa langkah pertama SOP?', $revised['question']);
        $this->assertSame('Cuci tangan', $revised['option_b']);
        $this->assertSame('b', $revised['correct_answer']);
    }

    public function test_revising_an_essay_keeps_its_answer_guide_as_text(): void
    {
        $this->fakeRevision([
            'question' => 'Jelaskan langkah SOP cuci tangan!',
            'correct_answer' => 'Basahi Tangan, Sabuni, Bilas',
            'explanation' => 'Urutan sesuai SOP.',
        ]);

        $revised = (new GeminiQuestionService('test-key'))->reviseQuestion([
            'type' => 'essay', 'question' => 'Sebutkan SOP cuci tangan', 'correct_answer' => 'Sabun', 'explanation' => '',
        ], 'all')['question'];

        $this->assertSame('essay', $revised['type']);
        $this->assertSame('Jelaskan langkah SOP cuci tangan!', $revised['question']);
        $this->assertSame('Basahi Tangan, Sabuni, Bilas', $revised['correct_answer']);
        $this->assertNull($revised['option_a']);
    }

    private static function geminiText(array $question): array
    {
        return ['candidates' => [['content' => ['parts' => [['text' => json_encode($question)]]]]]];
    }

    public function test_revision_rereads_the_material_and_lists_the_other_questions(): void
    {
        config(['filesystems.default' => 's3']);
        Storage::fake('s3')->put('dokumen/sop.txt', 'Langkah 1: cuci tangan dengan sabun.');
        $this->fakeRevision(['question' => 'Menurut SOP, apa yang dilakukan sebelum bekerja?']);

        $document = new Document(['name' => 'SOP']);
        $document->path = 'sop.txt';

        $result = (new GeminiQuestionService('test-key'))->reviseQuestion(self::CURRENT_QUESTION, 'all', null, [$document], ['Apa warna rambu peringatan?']);

        $this->assertSame('Menurut SOP, apa yang dilakukan sebelum bekerja?', $result['question']['question']);
        $this->assertNull($result['similar_to']);
        Http::assertSent(function (Request $request) {
            $parts = $request['contents'][0]['parts'];

            return str_contains($parts[0]['text'], 'Langkah 1: cuci tangan dengan sabun.')
                && str_contains($parts[1]['text'], 'Baca ulang materi')
                && str_contains($parts[1]['text'], 'Apa warna rambu peringatan?');
        });
    }

    public function test_a_question_that_already_exists_is_asked_again_once(): void
    {
        Http::fakeSequence('generativelanguage.googleapis.com/*')
            ->push(self::geminiText(['question' => 'Apa warna rambu peringatan K3?']))
            ->push(self::geminiText(['question' => 'Kapan APD wajib dipakai?']));

        $result = (new GeminiQuestionService('test-key'))->reviseQuestion(self::CURRENT_QUESTION, 'all', null, [], ['Apa warna rambu peringatan K3 ?']);

        $this->assertSame('Kapan APD wajib dipakai?', $result['question']['question']);
        $this->assertNull($result['similar_to']);
        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request) => str_contains($request['contents'][0]['parts'][0]['text'], 'terlalu mirip'));
    }

    public function test_a_question_that_stays_similar_is_reported(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(self::geminiText(['question' => 'Apa warna rambu peringatan K3?']))]);

        $result = (new GeminiQuestionService('test-key'))->reviseQuestion(self::CURRENT_QUESTION, 'all', null, [], ['apa warna rambu peringatan k3']);

        $this->assertSame('apa warna rambu peringatan k3', $result['similar_to']);
        Http::assertSentCount(2);
    }

    public function test_same_meaning_reported_by_the_ai_is_flagged_without_changing_the_meaning(): void
    {
        // Parafrase: teksnya beda jauh, tapi AI melaporkan masih menanyakan hal yang sama.
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(self::geminiText([
            'question' => 'Berdasarkan standar rambu K3, warna kuning menunjukkan petunjuk apa?',
            'duplicate_of' => 'Apa fungsi rambu K3 berwarna kuning?',
        ]))]);

        $result = (new GeminiQuestionService('test-key'))->reviseQuestion(self::CURRENT_QUESTION, 'question', null, [], ['Apa fungsi rambu K3 berwarna kuning?']);

        $this->assertSame('Apa fungsi rambu K3 berwarna kuning?', $result['similar_to']);
        // "Pertanyaan saja" tidak diminta ulang: menghindari soal kembar berarti mengganti makna, padahal pilihannya tetap.
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => str_contains($request['contents'][0]['parts'][0]['text'], '"duplicate_of": ""')
            && str_contains($request['contents'][0]['parts'][0]['text'], 'JANGAN mengubah makna pertanyaan'));
    }

    public function test_a_duplicate_the_ai_made_up_is_ignored(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(self::geminiText([
            'question' => 'Kapan APD wajib dipakai?',
            'duplicate_of' => 'Soal yang tidak ada di daftar',
        ]))]);

        $result = (new GeminiQuestionService('test-key'))->reviseQuestion(self::CURRENT_QUESTION, 'all', null, [], ['Apa fungsi rambu K3 berwarna kuning?']);

        $this->assertNull($result['similar_to']);
        Http::assertSentCount(1);
    }

    public function test_answers_only_revision_skips_the_duplicate_check(): void
    {
        $this->fakeRevision(['option_a' => 'Cuci tangan pakai sabun']);

        $result = (new GeminiQuestionService('test-key'))->reviseQuestion(self::CURRENT_QUESTION, 'answers', null, [], ['Apa langkah pertama SOP?']);

        $this->assertNull($result['similar_to']);
        Http::assertSentCount(1);
    }
}
