<?php

namespace App\Exports;

use App\Exports\Concerns\WritesTextAsText;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

/**
 * Template import peserta pelatihan. Judul kolomnya mengikuti file karyawan dari HR (Employee ID, Full Name, Job
 * Position) ditambah No HP yang wajib diisi.
 *
 * Sengaja tanpa baris contoh: import membuat user baru untuk Employee ID yang belum ada, jadi contoh yang lupa dihapus
 * akan menjadi user sungguhan. Kolom Employee ID dan No HP diformat teks supaya nol di depan No HP dan ID seperti
 * "2019.01.14.01" tidak diubah Excel menjadi angka saat diketik.
 */
class TrainingParticipantTemplate implements FromCollection, WithCustomValueBinder, WithEvents, WithHeadings
{
    use WritesTextAsText;

    /** Baris yang diformat teks di bawah judul kolom. */
    private const TEXT_ROWS = 1000;

    public function collection(): Collection
    {
        return collect();
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['Employee ID', 'Full Name', 'No HP', 'Job Position', 'Divisi'];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                foreach (['A', 'C'] as $column) {
                    $sheet->getStyle("{$column}2:{$column}".(self::TEXT_ROWS + 1))
                        ->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
                }
                foreach (range('A', 'E') as $column) {
                    $sheet->getColumnDimension($column)->setAutoSize(true);
                }

                self::restoreDefaultValueBinder();
            },
        ];
    }
}
