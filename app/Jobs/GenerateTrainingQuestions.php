<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\GeminiQuestionService;
use RuntimeException;

/**
 * Membuat soal kuis pelatihan dari dokumen materi dengan Gemini. PDF sampai 18MB dibaca utuh ke memori dan dikirim
 * sebagai base64, jadi dikerjakan worker AI; halaman soal mengambil hasilnya lewat polling (aiStatus).
 */
class GenerateTrainingQuestions extends AiTask
{
    /**
     * @param  array<int, int>  $documentIds
     */
    public function __construct(
        string $token,
        public array $documentIds,
        public int $questionCount,
        public string $questionType,
        public string $difficulty,
        public ?string $customPrompt = null,
        public ?string $apiKey = null,
    ) {
        parent::__construct($token);
    }

    protected function run(): array
    {
        $documents = Document::with('versions')->whereIn('id', $this->documentIds)->get();

        if ($documents->isEmpty()) {
            throw new RuntimeException('Dokumen yang dipilih tidak ditemukan.');
        }

        $questions = (new GeminiQuestionService($this->apiKey))->generateQuestions(
            $documents->all(),
            $this->questionCount,
            $this->questionType,
            $this->difficulty,
            $this->customPrompt
        );

        return ['count' => count($questions), 'questions' => $questions];
    }
}
