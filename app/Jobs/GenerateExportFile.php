<?php

namespace App\Jobs;

use App\Exports\LearningModuleQuestionsExport;
use App\Exports\LearningModuleRekapExport;
use App\Exports\ScoreRecapExport;
use App\Exports\TrainingQuestionsExport;
use App\Exports\TrainingRekapExport;
use App\Exports\UserExport;
use App\Models\LearningModule;
use App\Models\Training;
use App\Notifications\BackgroundTaskFinished;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Membuat file export Excel di disk "local" (folder exports/) lalu mengirim link unduhnya lewat notifikasi.
 * File dihapus otomatis oleh notifications:prune setelah EXPORT_TTL_DAYS hari.
 */
class GenerateExportFile extends BackgroundTask
{
    public const USERS = 'user-export';

    public const TRAINING = 'training-export';

    public const LEARNING_MODULE = 'learning-module-export';

    public const TRAINING_QUESTIONS = 'training-questions-export';

    public const LEARNING_MODULE_QUESTIONS = 'learning-module-questions-export';

    public const SCORES = 'score-recap-export';

    public const DIRECTORY = 'exports';

    public const EXPORT_TTL_DAYS = 7;

    public const QUEUED_MESSAGE = 'Export sedang diproses di belakang layar. File akan tersedia di menu Notifikasi begitu selesai, dan Anda akan dikabari lewat WhatsApp/email.';

    /**
     * @param  array<string, mixed>  $filters  hanya untuk SCORES: from, to (Y-m-d), sections, divisi_id
     */
    public function __construct(int $userId, public string $type, public ?int $modelId = null, public array $filters = [])
    {
        parent::__construct($userId);
    }

    public function kind(): string
    {
        return $this->type;
    }

    public function uniqueId(): string
    {
        return parent::uniqueId().':'.$this->modelId.':'.md5(json_encode($this->filters));
    }

    protected function failureTitle(): string
    {
        return 'Export gagal';
    }

    protected function process(): BackgroundTaskFinished
    {
        [$export, $filename, $title] = match ($this->type) {
            self::USERS => [new UserExport, 'user_export_'.date('Ymd_His').'.xlsx', 'Export user selesai'],
            self::TRAINING => $this->training(),
            self::LEARNING_MODULE => $this->learningModule(),
            self::TRAINING_QUESTIONS => $this->trainingQuestions(),
            self::LEARNING_MODULE_QUESTIONS => $this->learningModuleQuestions(),
            self::SCORES => $this->scores(),
            default => throw new InvalidArgumentException("Jenis export tidak dikenal: {$this->type}"),
        };

        $path = self::DIRECTORY.'/'.Str::uuid().'/'.$filename;
        Excel::store($export, $path, 'local');

        return new BackgroundTaskFinished(
            $this->kind(),
            BackgroundTaskFinished::STATUS_SUCCESS,
            $title,
            "File {$filename} sudah siap diunduh.",
            downloadPath: $path,
            downloadName: $filename,
        );
    }

    /**
     * @return array{0: TrainingRekapExport, 1: string, 2: string}
     */
    private function training(): array
    {
        $training = Training::with(['trainer', 'participants.user.divisi', 'participants.quizResult', 'participants.pretestResult'])->findOrFail($this->modelId);

        return [
            new TrainingRekapExport($training),
            'rekap_pelatihan_'.Str::slug($training->title).'_'.date('Ymd_His').'.xlsx',
            'Export rekap pelatihan selesai',
        ];
    }

    /**
     * @return array{0: LearningModuleRekapExport, 1: string, 2: string}
     */
    private function learningModule(): array
    {
        $module = LearningModule::findOrFail($this->modelId);

        return [
            new LearningModuleRekapExport($module),
            'rekap_modul_'.Str::slug($module->title).'_'.date('Ymd_His').'.xlsx',
            'Export rekap modul selesai',
        ];
    }

    /**
     * @return array{0: TrainingQuestionsExport, 1: string, 2: string}
     */
    private function trainingQuestions(): array
    {
        $training = Training::findOrFail($this->modelId);

        return [
            new TrainingQuestionsExport($training),
            'soal_pelatihan_'.Str::slug($training->title).'_'.date('Ymd_His').'.xlsx',
            'Export soal pelatihan selesai',
        ];
    }

    /**
     * @return array{0: LearningModuleQuestionsExport, 1: string, 2: string}
     */
    private function learningModuleQuestions(): array
    {
        $module = LearningModule::findOrFail($this->modelId);

        return [
            new LearningModuleQuestionsExport($module),
            'soal_modul_'.Str::slug($module->title).'_'.date('Ymd_His').'.xlsx',
            'Export soal modul selesai',
        ];
    }

    /**
     * @return array{0: ScoreRecapExport, 1: string, 2: string}
     */
    private function scores(): array
    {
        $from = CarbonImmutable::parse($this->filters['from']);
        $to = CarbonImmutable::parse($this->filters['to']);

        return [
            new ScoreRecapExport($from, $to, $this->filters['sections'], $this->filters['divisi_id'] ?? null),
            'rekap_nilai_'.$from->format('Ymd').'-'.$to->format('Ymd').'_'.date('Ymd_His').'.xlsx',
            'Export rekap nilai selesai',
        ];
    }
}
