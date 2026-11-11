<?php

namespace App\Jobs;

use App\Imports\LearningModuleQuestionImport;
use App\Imports\TrainingQuestionImport;
use App\Models\LearningModule;
use App\Models\LearningModuleQuestion;
use App\Models\Training;
use App\Notifications\BackgroundTaskFinished;
use App\Services\MaterialQuizService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;
use Throwable;

/**
 * Import soal kuis (pelatihan atau modul belajar) dari file Excel/CSV yang sudah diunggah ke disk "local".
 *
 * Membaca spreadsheet memakan memori sebanding isi filenya, jadi tidak dikerjakan di request web. Seluruh file
 * diimpor dalam satu transaksi: satu baris yang salah membatalkan semuanya.
 */
class ProcessQuestionImport extends BackgroundTask
{
    public const TRAINING = 'training-question-import';

    public const LEARNING_MODULE = 'learning-module-question-import';

    public function __construct(int $userId, public string $type, public int $modelId, public string $path)
    {
        parent::__construct($userId);
    }

    public function kind(): string
    {
        return $this->type;
    }

    public function uniqueId(): string
    {
        return parent::uniqueId().':'.$this->modelId;
    }

    protected function failureTitle(): string
    {
        return 'Import soal gagal';
    }

    protected function process(): BackgroundTaskFinished
    {
        try {
            [$count, $target] = match ($this->type) {
                self::TRAINING => $this->training(),
                self::LEARNING_MODULE => $this->learningModule(),
                default => throw new InvalidArgumentException("Jenis import soal tidak dikenal: {$this->type}"),
            };
        } catch (Throwable $e) {
            throw new RuntimeException($e->getMessage().' Tidak ada soal dari file ini yang disimpan.', previous: $e);
        }

        return new BackgroundTaskFinished(
            $this->kind(),
            BackgroundTaskFinished::STATUS_SUCCESS,
            'Import soal selesai',
            "Berhasil mengimpor {$count} soal ke {$target}. Muat ulang halaman soal untuk melihatnya.",
        );
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function training(): array
    {
        $training = Training::findOrFail($this->modelId);
        $import = new TrainingQuestionImport($training->id);

        DB::transaction(fn () => Excel::import($import, $this->path, 'local'));

        return [$import->getImportedCount(), 'kuis pelatihan "'.$training->title.'"'];
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function learningModule(): array
    {
        $module = LearningModule::findOrFail($this->modelId);
        $nextSortOrder = (int) LearningModuleQuestion::where('learning_module_id', $module->id)->max('sort_order') + 1;
        $import = new LearningModuleQuestionImport($module, $nextSortOrder);

        DB::transaction(fn () => Excel::import($import, $this->path, 'local'));
        app(MaterialQuizService::class)->refresh($module);

        return [$import->getImportedCount(), 'kuis "'.$module->title.'"'];
    }

    protected function cleanup(): void
    {
        Storage::disk('local')->delete($this->path);
    }
}
