<?php

namespace App\Exports;

use App\Models\Training;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Jawaban form evaluasi: satu baris per peserta yang mengisi, satu kolom per pertanyaan pelatihan ini.
 * Butuh relasi evaluationQuestions, participants.evaluation, dan participants.user (divisi, position, area, roles).
 *
 * Jawaban teks diketik peserta, jadi setiap teks ditulis apa adanya sebagai teks: jawaban berawalan "=" tidak
 * menjadi rumus (yang bisa menggagalkan export atau dijalankan Excel) dan "+62..." tidak berubah menjadi angka.
 */
class TrainingEvaluationSheet extends DefaultValueBinder implements FromArray, ShouldAutoSize, WithCustomValueBinder, WithEvents, WithHeadings, WithStyles, WithTitle
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
            ...$this->training->evaluationQuestions->pluck('question')->all(),
            'Rata-rata Skala',
        ];
    }

    public function array(): array
    {
        $questions = $this->training->evaluationQuestions;
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
                ...$questions->map(fn ($question) => $evaluation->answerTo($question) ?? '-')->all(),
                $evaluation->average($questions) ?? '-',
            ];
        }

        return $rows;
    }

    public function bindValue(Cell $cell, mixed $value): bool
    {
        if (is_string($value)) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    /**
     * Pengikat nilai berlaku global di PhpSpreadsheet, jadi dikembalikan ke bawaan begitu sheet ini selesai agar
     * export lain di proses yang sama tidak ikut terpengaruh.
     */
    public function registerEvents(): array
    {
        return [AfterSheet::class => fn () => self::restoreDefaultValueBinder()];
    }

    public static function restoreDefaultValueBinder(): void
    {
        Cell::setValueBinder(app(config('excel.value_binder.default', DefaultValueBinder::class)));
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
