<?php

namespace App\Exports;

use App\Models\Training;
use App\Models\TrainingQuestion;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Bank soal pelatihan beserta kunci jawabannya, dengan kolom yang sama seperti TrainingQuestionTemplate sehingga
 * file bisa diedit lalu diimpor ulang. Kolom "catatan" di ujung diabaikan oleh importer.
 */
class TrainingQuestionsExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    public const IMAGE_NOTE = 'Ada gambar soal/pilihan; gambar tidak ikut diekspor, tambahkan lewat Edit setelah import.';

    public function __construct(protected Training $training) {}

    public function collection(): Collection
    {
        return $this->training->questions()->orderBy('id')->get();
    }

    public function headings(): array
    {
        return [...(new TrainingQuestionTemplate)->headings(), 'catatan'];
    }

    /**
     * @param  TrainingQuestion  $question
     */
    public function map($question): array
    {
        $isEssay = $question->type === 'essay';

        return [
            $isEssay ? 'ESSAY' : 'PG',
            $question->question,
            $isEssay ? (string) $question->correct_answer : strtoupper((string) $question->correct_answer),
            ...array_map(fn (string $letter) => $isEssay ? '' : (string) $question->{'option_'.$letter}, TrainingQuestion::OPTION_LETTERS),
            (string) $question->explanation,
            $question->hasImages() ? self::IMAGE_NOTE : '',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return (new TrainingQuestionTemplate)->styles($sheet);
    }
}
