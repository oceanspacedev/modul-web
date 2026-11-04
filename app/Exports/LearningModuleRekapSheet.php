<?php

namespace App\Exports;

use App\Models\LearningModule;
use App\Support\LearningModuleProgress;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class LearningModuleRekapSheet implements FromArray, WithEvents, WithTitle
{
    const HEADING_ROW = 6;

    const DATA_START = 7;

    const LAST_COL = 'M';

    /**
     * @param  Collection<int, LearningModuleProgress>  $participants
     */
    public function __construct(protected LearningModule $module, protected Collection $participants) {}

    public function title(): string
    {
        return 'Rekap Peserta';
    }

    public function array(): array
    {
        $rows = [];

        $rows[] = ['REKAP HASIL MODUL BELAJAR'];
        $rows[] = ['Judul Modul', $this->module->title];
        $rows[] = ['Sasaran', $this->module->targetLabel()];
        $rows[] = ['Nilai Minimal Lulus', $this->module->passing_score];
        $rows[] = ['Total Peserta', $this->participants->count().' orang'];

        $rows[] = [
            'No', 'ID Karyawan', 'Nama Peserta', 'Divisi', 'Jabatan', 'Progres Materi',
            'Nilai Pretest', 'Jumlah Posttest', 'Posttest Terbaik', 'Posttest Terakhir',
            'Kenaikan Nilai', 'Status', 'Terakhir Mengerjakan',
        ];

        foreach ($this->participants->values() as $index => $progress) {
            $user = $progress->user;
            $improvement = $progress->improvement();

            $rows[] = [
                $index + 1,
                $user->employee_id ?? '-',
                $user->nama_lengkap ?? '-',
                $user->divisi->name ?? '-',
                $user->jabatan->name ?? '-',
                $progress->completedItemCount().'/'.$progress->totalItemCount(),
                $progress->pretest?->score ?? '-',
                $progress->posttests->count(),
                $progress->bestPosttest()?->score ?? '-',
                $progress->latestPosttest()?->score ?? '-',
                $improvement === null ? '-' : ($improvement > 0 ? '+' : '').$improvement,
                $progress->statusLabel(),
                $progress->lastActivityAt()?->format('d-m-Y H:i') ?? '-',
            ];
        }

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = self::DATA_START + $this->participants->count() - 1;
                $hRow = self::HEADING_ROW;
                $lastCol = self::LAST_COL;

                $sheet->mergeCells('A1:'.$lastCol.'1');
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 13],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);
                $sheet->getStyle('A2:A5')->getFont()->setBold(true);

                $sheet->getRowDimension($hRow)->setRowHeight(28);
                $sheet->getStyle('A'.$hRow.':'.$lastCol.$hRow)->applyFromArray([
                    'font' => ['bold' => true, 'size' => 10],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                    'borders' => [
                        'bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => 'FF000000']],
                    ],
                ]);

                if ($this->participants->isNotEmpty()) {
                    $sheet->getStyle('A'.self::DATA_START.':'.$lastCol.$lastRow)->applyFromArray([
                        'borders' => [
                            'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFD0D0D0']],
                        ],
                        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                    ]);
                    $sheet->getStyle('F'.self::DATA_START.':K'.$lastRow)
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }

                $widths = [
                    'A' => 5, 'B' => 14, 'C' => 28, 'D' => 20, 'E' => 16, 'F' => 14, 'G' => 13,
                    'H' => 13, 'I' => 14, 'J' => 14, 'K' => 13, 'L' => 20, 'M' => 18,
                ];
                foreach ($widths as $col => $width) {
                    $sheet->getColumnDimension($col)->setWidth($width);
                }
            },
        ];
    }
}
