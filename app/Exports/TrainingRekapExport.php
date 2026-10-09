<?php

namespace App\Exports;

use App\Models\Training;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class TrainingRekapExport implements FromArray, WithEvents, WithTitle
{
    protected Training $training;

    const HEADING_ROW = 7;

    const DATA_START = 8;

    const LAST_COL = 'Q';

    public function __construct(Training $training)
    {
        $this->training = $training;
    }

    public function title(): string
    {
        return 'Rekap Pelatihan';
    }

    public function array(): array
    {
        $training = $this->training;
        $rows = [];

        $rows[] = ['REKAP HASIL PELATIHAN'];
        $rows[] = ['Topik Pelatihan', $training->title];
        $rows[] = ['Pemateri',        $training->trainer->full_name ?? '-'];
        $rows[] = ['Tanggal',         Carbon::parse($training->training_date)->format('d-m-Y')];
        $rows[] = ['Waktu',           substr($training->start_time, 0, 5).' - '.substr($training->end_time, 0, 5).' WIB'];
        $rows[] = ['Total Peserta',   $training->participants->count().' orang'];

        $rows[] = [
            'No', 'ID Karyawan', 'Nama Peserta', 'Divisi', 'No WhatsApp',
            'Status Kehadiran', 'Waktu Absen', 'Bukti Screenshot', 'Status Kuis',
            'Nilai PG (0-100)', 'Status Essay', 'Nilai Essay (0-100)', 'Nilai Akhir (0-100)',
            'Catatan Essay', 'Pelanggaran Tab', 'Status Submit', 'Waktu Submit Kuis',
        ];

        $no = 1;
        foreach ($training->participants as $participant) {
            $user = $participant->user;
            $quiz = $participant->quizResult;

            $essayStatusLabel = 'Tidak Ada Essay';
            if ($quiz) {
                $essayStatusLabel = match ($quiz->essay_status) {
                    'graded' => 'Sudah Dinilai',
                    'pending' => 'Menunggu Dinilai',
                    default => 'Tidak Ada Essay',
                };
            }

            $offCamSuffix = '';
            if ($participant->is_off_cam) {
                $count = $participant->off_cam_count ?: 1;
                $offCamSuffix = " (Off Cam {$count}x)";
            }

            $attendanceLabel = match ($participant->attendance_status) {
                'hadir' => 'Hadir'.$offCamSuffix,
                'tidak_hadir' => 'Tidak Hadir'.$offCamSuffix,
                default => 'Belum Absen'.$offCamSuffix,
            };

            $rows[] = [
                $no++,
                $user->id_karyawan ?? '-',
                $user->full_name ?? '-',
                $user->divisi->name ?? '-',
                $user->no_wa ?? '-',
                $attendanceLabel,
                $participant->attended_at
                    ? Carbon::parse($participant->attended_at)->format('d-m-Y H:i')
                    : '-',
                $participant->attendance_proof
                    ? asset('storage/'.$participant->attendance_proof)
                    : '-',
                $quiz ? 'Sudah Mengerjakan' : 'Belum Mengerjakan',
                $quiz ? (float) ($quiz->mc_score ?? $quiz->score) : 0,
                $essayStatusLabel,
                $quiz && $quiz->essay_score !== null ? (float) $quiz->essay_score : '-',
                $quiz ? (float) $quiz->score : 0,
                $quiz ? ($quiz->essay_feedback ?? '-') : '-',
                $quiz ? (($quiz->tab_switch_count ?? 0).' kali') : '-',
                $quiz ? ($quiz->is_force_submitted ? 'Auto-Submit (Melanggar)' : 'Normal') : '-',
                $quiz && $quiz->submitted_at
                    ? Carbon::parse($quiz->submitted_at)->format('d-m-Y H:i')
                    : '-',
            ];
        }

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $totalRows = $this->training->participants->count();
                $lastRow = self::DATA_START + $totalRows - 1;
                $hRow = self::HEADING_ROW;
                $dStart = self::DATA_START;
                $lastCol = self::LAST_COL;

                // ── Judul baris 1: bold, rata tengah, merge ───────────────────
                $sheet->mergeCells('A1:'.$lastCol.'1');
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 13],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                // ── Label info baris 2-6: bold ────────────────────────────────
                $sheet->getStyle('A2:A6')->getFont()->setBold(true);

                // ── Heading kolom baris 8: bold, rata tengah, border bawah ───
                $sheet->getRowDimension($hRow)->setRowHeight(28);
                $sheet->getStyle('A'.$hRow.':'.$lastCol.$hRow)->applyFromArray([
                    'font' => ['bold' => true, 'size' => 10],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                    'borders' => [
                        'bottom' => [
                            'borderStyle' => Border::BORDER_MEDIUM,
                            'color' => ['argb' => 'FF000000'],
                        ],
                    ],
                ]);

                // ── Data rows: border tipis antar sel ─────────────────────────
                if ($totalRows > 0) {
                    $sheet->getStyle('A'.$dStart.':'.$lastCol.$lastRow)->applyFromArray([
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color' => ['argb' => 'FFD0D0D0'],
                            ],
                        ],
                        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                    ]);

                    // Rata tengah kolom No dan nilai
                    foreach (['A', 'F', 'J', 'L', 'M'] as $col) {
                        $sheet->getStyle($col.$dStart.':'.$col.$lastRow)
                            ->getAlignment()
                            ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    }
                }

                // ── Lebar kolom ───────────────────────────────────────────────
                $widths = [
                    'A' => 5,  'B' => 14,  'C' => 28,  'D' => 20,
                    'E' => 16,  'F' => 15,  'G' => 16,  'H' => 40,
                    'I' => 18,  'J' => 13,  'K' => 18,  'L' => 14,
                    'M' => 14,  'N' => 28,  'O' => 15,  'P' => 22,
                    'Q' => 18,
                ];
                foreach ($widths as $col => $width) {
                    $sheet->getColumnDimension($col)->setWidth($width);
                }
            },
        ];
    }
}
