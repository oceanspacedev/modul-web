<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LearningModuleQuestionTemplate implements FromArray, ShouldAutoSize, WithHeadings, WithStyles
{
    use Exportable;

    public function headings(): array
    {
        return [
            'pertanyaan',      // Isi pertanyaan
            'kunci_jawaban',   // A, B, C, D, atau E
            'pilihan_a',       // Opsi A
            'pilihan_b',       // Opsi B
            'pilihan_c',       // Opsi C (opsional)
            'pilihan_d',       // Opsi D (opsional)
            'pilihan_e',       // Opsi E (opsional)
            'penjelasan',      // Catatan/pembahasan materi (opsional)
        ];
    }

    public function array(): array
    {
        return [
            [
                'Apa tujuan utama dari proses verifikasi modul dokumen?',
                'A',
                'Memastikan keabsahan dan keakuratan informasi dokumen',
                'Menghapus dokumen lama secara otomatis',
                'Mengganti format dokumen ke PDF',
                'Mengarsipkan dokumen tanpa persetujuan',
                '',
                'Verifikasi memastikan dokumen valid sebelum dipublikasikan.',
            ],
            [
                'Mana dari pilihan berikut yang merupakan departemen pendukung operasional?',
                'C',
                'Marketing',
                'Sales Direct',
                'Human Resource (HR)',
                'Procurement',
                'General Affair (GA)',
                'Pilihan A-E didukung; minimal isi Pilihan A dan B.',
            ],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '1E40AF'], // Navy Blue
                ],
            ],
        ];
    }
}
