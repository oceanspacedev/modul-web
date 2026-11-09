<?php

namespace App\Jobs;

use App\Models\LearningModuleItem;
use App\Services\GeminiQuestionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Membuat soal pilihan ganda dari materi modul (dokumen dan/atau video) dengan Gemini.
 *
 * Video bisa butuh beberapa menit (unggah + diproses Gemini), jadi berjalan di koneksi queue AI yang batas waktunya
 * panjang. Hasil ditaruh di cache dan diambil halaman soal lewat polling.
 */
class GenerateLearningModuleQuestions implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    /**
     * @param  array<int, int>  $itemIds
     */
    public function __construct(
        public string $token,
        public int $moduleId,
        public array $itemIds,
        public int $questionCount,
        public string $difficulty,
        public ?string $customPrompt = null,
    ) {
        $this->onConnection(config('queue.ai_connection'));
    }

    public static function cacheKey(string $token): string
    {
        return 'learning-module-ai:'.$token;
    }

    /**
     * Dokumen selalu bisa dibaca; video hanya file upload atau link YouTube.
     */
    public static function supports(LearningModuleItem $item): bool
    {
        return ! $item->isVideo() || GeminiQuestionService::supportsVideo($item->materialable);
    }

    /**
     * Materi (Document/Video) dari item modul yang dipilih, sesuai urutan modul.
     *
     * @param  array<int, int>  $itemIds
     */
    public static function sources(int $moduleId, array $itemIds): array
    {
        return LearningModuleItem::with('materialable')
            ->where('learning_module_id', $moduleId)
            ->whereIn('id', $itemIds)
            ->orderBy('sort_order')
            ->get()
            ->pluck('materialable')
            ->filter()
            ->all();
    }

    public function handle(): void
    {
        try {
            $questions = (new GeminiQuestionService)->generateQuestions(
                self::sources($this->moduleId, $this->itemIds),
                $this->questionCount,
                'multiple_choice',
                $this->difficulty,
                $this->customPrompt
            );

            $this->storeResult(['status' => 'done', 'questions' => $questions]);
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
        Cache::put(self::cacheKey($this->token), $result, now()->addHours(2));
    }
}
