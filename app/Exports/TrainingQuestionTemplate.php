<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TrainingQuestionTemplate implements FromArray, ShouldAutoSize, WithHeadings, WithStyles
{
    use Exportable;

    public function headings(): array
    {
        return [
            'tipe_soal',       // PG atau ESSAY
            'pertanyaan',      // Isi pertanyaan
            'kunci_jawaban',   // A, B, C, D, E, F untuk PG; atau panduan jawaban untuk Essay
            'pilihan_a',       // Opsi A
            'pilihan_b',       // Opsi B
            'pilihan_c',       // Opsi C (opsional)
            'pilihan_d',       // Opsi D (opsional)
            'pilihan_e',       // Opsi E (opsional)
            'pilihan_f',       // Opsi F (opsional)
            'penjelasan',      // Catatan/pembahasan materi (opsional)
        ];
    }

    public function array(): array
    {
        return [
            [
                'PG',
                'Apa tujuan utama dari proses verifikasi modul dokumen?',
                'A',
                'Memastikan keabsahan dan keakuratan informasi dokumen',
                'Menghapus dokumen lama secara otomatis',
                'Mengganti format dokumen ke PDF',
                'Mengarsipkan dokumen tanpa persetujuan',
                '',
                '',
                'Verifikasi memastikan dokumen valid sebelum dipublikasikan.',
            ],
            [
                'PG',
                'Apakah kehadiran sesi Zoom wajib dicatat sebelum mengerjakan kuis evaluasi?',
                'A',
                'Ya, wajib hadir',
                'Tidak wajib',
                '',
                '',
                '',
                '',
                'Hanya peserta yang hadir yang berhak mengikuti evaluasi.',
            ],
            [
                'PG',
                'Mana dari pilihan berikut yang merupakan departemen pendukung operasional?',
                'C',
                'Marketing',
                'Sales Direct',
                'Human Resource (HR)',
                'Procurement',
                'General Affair (GA)',
                'Legal & Compliance',
                'Pilihan A-F didukung secara fleksibel oleh sistem.',
            ],
            [
                'ESSAY',
                'Jelaskan secara ringkas alur pengerjaan kuis setelah peserta mengikuti sesi pelatihan di Zoom!',
                'Peserta membuka link portal unik, konfirmasi kehadiran, lalu mengisi kuis evaluasi materi.',
                '',
                '',
                '',
                '',
                '',
                '',
                'Kolom pilihan A-F dikosongkan untuk soal bertipe ESSAY.',
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
