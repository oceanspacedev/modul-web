<?php

namespace App\Jobs;

use App\Models\Document;
use App\Models\Training;
use App\Services\GeminiQuestionService;

/**
 * "Ubah dengan AI" untuk satu soal kuis pelatihan: AI membaca ulang dokumen materi dan memastikan pertanyaannya belum
 * ada. Dikerjakan worker AI seperti GenerateTrainingQuestions; hasil diambil lewat polling (aiStatus).
 */
class ReviseTrainingQuestion extends AiTask
{
    /**
     * @param  array<int, int>  $documentIds  Kosong: pakai dokumen milik pelatihan
     * @param  array<string, mixed>  $question
     * @param  array<int, string>  $otherQuestions  Pertanyaan soal lain di layar tinjauan
     * @param  int|null  $questionId  Soal tersimpan yang sedang diedit; tidak dihitung sebagai soal kembar
     */
    public function __construct(
        string $token,
        public int $trainingId,
        public array $documentIds,
        public array $question,
        public string $part,
        public ?string $instruction = null,
        public array $otherQuestions = [],
        public ?int $questionId = null,
        public ?string $apiKey = null,
    ) {
        parent::__construct($token);
    }

    protected function run(): array
    {
        $training = Training::findOrFail($this->trainingId);

        $documents = $this->documentIds !== []
            ? Document::with('versions')->whereIn('id', $this->documentIds)->get()
            : $training->documents()->with('versions')->get();

        $savedQuestions = $training->questions()
            ->when($this->questionId, fn ($query, $id) => $query->whereKeyNot($id))
            ->pluck('question')
            ->all();

        return (new GeminiQuestionService($this->apiKey))->reviseQuestion(
            $this->question,
            $this->part,
            $this->instruction,
            $documents->all(),
            [...$this->otherQuestions, ...$savedQuestions]
        );
    }
}
