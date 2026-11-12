<?php

namespace App\Exports;

use App\Models\Training;
use App\Support\TrainingEvaluationForm;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Jawaban form evaluasi: satu baris per peserta yang mengisi, satu kolom per pertanyaan (skala 1-5).
 * Butuh relasi participants.evaluation dan participants.user (divisi, position, area, roles).
 */
class TrainingEvaluationSheet implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    public function __construct(protected Training $training) {}

    public function title(): string
    {
        return 'Evaluasi';
    }

    public function headings(): array
    {
        return [
            'No', 'ID Karyawan', 'Nama Peserta', 'Jabatan', 'Posisi', 'Divisi', 'Area', 'Tanggal Pelatihan', 'Materi', 'Waktu Isi',
            ...array_values(TrainingEvaluationForm::questions()),
            'Rata-rata', 'Masukan',
        ];
    }

    public function array(): array
    {
        $rows = [];
        $no = 1;

        foreach ($this->training->participants as $participant) {
            $evaluation = $participant->evaluation;
            if (! $evaluation) {
                continue;
            }

            $user = $participant->user;
            $rows[] = [
                $no++,
                $user->employee_id ?? '-',
                $user->nama_lengkap ?? '-',
                $user?->jabatan?->name ?? '-',
                $user?->position?->name ?? '-',
                $user?->divisi?->name ?? '-',
                $user?->area?->name ?? '-',
                $this->training->training_date->format('d-m-Y'),
                $this->training->title,
                $evaluation->submitted_at->format('d-m-Y H:i'),
                ...array_map(fn (string $key) => $evaluation->ratings[$key] ?? '-', array_keys(TrainingEvaluationForm::questions())),
                $evaluation->average() ?? '-',
                $evaluation->comment ?? '-',
            ];
        }

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
