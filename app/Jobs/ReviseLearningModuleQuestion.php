<?php

namespace App\Jobs;

use App\Models\LearningModuleQuestion;
use App\Services\GeminiQuestionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * "Ubah dengan AI" untuk satu soal di layar tinjauan soal AI modul: AI membaca ulang materi (dokumen dan/atau video)
 * dan memastikan pertanyaannya belum ada. Video bisa butuh beberapa menit, jadi berjalan di koneksi queue AI seperti
 * GenerateLearningModuleQuestions; hasil ditaruh di cache dengan kunci yang sama sehingga diambil lewat aiStatus.
 */
class ReviseLearningModuleQuestion implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    /**
     * @param  array<int, int>  $itemIds
     * @param  array<int, string>  $otherQuestions  Pertanyaan soal lain di layar tinjauan
     * @param  int|null  $questionId  Soal tersimpan yang sedang diedit; tidak dihitung sebagai soal kembar
     */
    public function __construct(
        public string $token,
        public int $moduleId,
        public array $itemIds,
        public array $question,
        public string $part,
        public ?string $instruction = null,
        public array $otherQuestions = [],
        public ?int $questionId = null,
    ) {
        $this->onConnection(config('queue.ai_connection'));
    }

    public function handle(): void
    {
        try {
            $savedQuestions = LearningModuleQuestion::where('learning_module_id', $this->moduleId)
                ->when($this->questionId, fn ($query, $id) => $query->whereKeyNot($id))
                ->pluck('question')
                ->all();

            $result = (new GeminiQuestionService)->reviseQuestion(
                [...$this->question, 'type' => 'multiple_choice'],
                $this->part,
                $this->instruction,
                GenerateLearningModuleQuestions::sources($this->moduleId, $this->itemIds),
                [...$this->otherQuestions, ...$savedQuestions]
            );

            $this->storeResult(['status' => 'done', ...$result]);
        } catch (Throwable $e) {
            report($e);
            $this->storeResult(['status' => 'failed', 'message' => $e->getMessage()]);
        }
    }

    /**
     * Worker dihentikan (mis. melewati batas waktu) sebelum handle() sempat menyimpan hasil.
     */
    public function failed(?Throwable $exception): void
    {
        $this->storeResult([
            'status' => 'failed',
            'message' => 'Proses AI terhenti sebelum selesai. Coba lagi dengan materi yang lebih sedikit atau video yang lebih pendek.',
        ]);
    }

    private function storeResult(array $result): void
    {
        Cache::put(GenerateLearningModuleQuestions::cacheKey($this->token), $result, now()->addHours(2));
    }
}
