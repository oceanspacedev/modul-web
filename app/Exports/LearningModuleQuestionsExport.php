<?php

namespace App\Exports;

use App\Exports\Concerns\WritesTextAsText;
use App\Models\LearningModule;
use App\Models\LearningModuleQuestion;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Bank soal pretest/posttest modul (juga kuis video/dokumen) beserta kunci jawabannya, dengan kolom yang sama seperti
 * LearningModuleQuestionTemplate sehingga file bisa diedit lalu diimpor ulang. Kolom "catatan" diabaikan importer.
 */
class LearningModuleQuestionsExport implements FromCollection, ShouldAutoSize, WithCustomValueBinder, WithEvents, WithHeadings, WithMapping, WithStyles
{
    use WritesTextAsText;

    public function __construct(protected LearningModule $module) {}

    public function collection(): Collection
    {
        return $this->module->questions()->get();
    }

    public function headings(): array
    {
        return [...(new LearningModuleQuestionTemplate)->headings(), 'catatan'];
    }

    /**
     * @param  LearningModuleQuestion  $question
     */
    public function map($question): array
    {
        return [
            $question->question,
            strtoupper((string) $question->correct_answer),
            ...array_map(fn (string $letter) => (string) $question->{'option_'.$letter}, LearningModuleQuestion::OPTION_LETTERS),
            (string) $question->explanation,
            $question->hasImages() ? TrainingQuestionsExport::IMAGE_NOTE : '',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return (new LearningModuleQuestionTemplate)->styles($sheet);
    }
}
