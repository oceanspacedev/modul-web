<?php

namespace App\Exports;

use App\Exports\Concerns\WritesTextAsText;
use App\Models\LearningModuleAttempt;
use App\Support\LearningModuleProgress;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Setiap percobaan pretest/posttest, termasuk percobaan ulang.
 */
class LearningModuleAttemptsSheet implements FromArray, ShouldAutoSize, WithCustomValueBinder, WithEvents, WithHeadings, WithStyles, WithTitle
{
    use WritesTextAsText;

    /**
     * @param  Collection<int, LearningModuleProgress>  $participants
     */
    public function __construct(protected Collection $participants) {}

    public function title(): string
    {
        return 'Riwayat Percobaan';
    }

    public function headings(): array
    {
        return ['ID Karyawan', 'Nama Peserta', 'Divisi', 'Fase', 'Percobaan Ke', 'Benar', 'Total Soal', 'Nilai', 'Status', 'Waktu Submit'];
    }

    public function array(): array
    {
        return $this->participants
            ->flatMap(fn (LearningModuleProgress $progress) => $progress->pretests->merge($progress->posttests)
                ->map(fn (LearningModuleAttempt $attempt) => [
                    $progress->user->employee_id ?? '-',
                    $progress->user->nama_lengkap ?? '-',
                    $progress->user->divisi->name ?? '-',
                    $attempt->isPretest() ? 'Pretest' : 'Posttest',
                    $attempt->attempt_number,
                    $attempt->correct_answers,
                    $attempt->total_questions,
                    $attempt->score,
                    $attempt->is_passed ? 'Lulus' : 'Belum Lulus',
                    $attempt->submitted_at->format('d-m-Y H:i'),
                ]))
            ->all();
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
