<?php

namespace App\Exports;

use App\Exports\Concerns\WritesTextAsText;
use App\Models\Training;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

/**
 * Rekap satu pelatihan. Kolomnya mengikuti pengaturan aktif pelatihan: hanya langkah yang dipakai (presensi, pretest,
 * form evaluasi, posttest) yang mendapat kolom, dalam Urutan Langkah Peserta (lihat TrainingStepColumns).
 */
class TrainingRekapExport implements FromArray, WithCustomValueBinder, WithEvents, WithTitle
{
    use WritesTextAsText;

    protected Training $training;

    const HEADING_ROW = 7;

    const DATA_START = 8;

    private const BASE_HEADINGS = ['No', 'ID Karyawan', 'Nama Peserta', 'Divisi', 'No WhatsApp'];

    /** Lebar kolom menurut judulnya; judul lain memakai DEFAULT_WIDTH. */
    private const WIDTHS = [
        'No' => 5, 'ID Karyawan' => 14, 'Nama Peserta' => 28, 'Divisi' => 20, 'No WhatsApp' => 16,
        'Status Kehadiran' => 15, 'Waktu Absen' => 16, 'Bukti Screenshot' => 18,
    ];

    private const DEFAULT_WIDTH = 18;

    public function __construct(Training $training)
    {
        $this->training = $training;

        // Kolom kuis mengikuti soalnya dan kolom evaluasi butuh relasi ini; pemanggil yang belum memuatnya tidak
        // perlu tahu.
        $training->loadMissing(['questions', ...($training->has_evaluation ? ['evaluationQuestions', 'participants.evaluation'] : [])]);
    }

    public function title(): string
    {
        return 'Rekap Pelatihan';
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return [
            ...self::BASE_HEADINGS,
            ...($this->hasOffCamColumn() ? ['Off Cam'] : []),
            ...array_merge([], ...array_map(fn (string $step) => TrainingStepColumns::headings($step, detailed: true, essay: TrainingStepColumns::hasEssay($this->training, $step)), TrainingStepColumns::stepsOf($this->training))),
        ];
    }

    /**
     * Off Cam biasanya menempel di kolom Status Kehadiran. Pelatihan daring tanpa presensi tidak punya kolom itu,
     * jadi catatan Off Cam-nya mendapat kolom sendiri.
     */
    private function hasOffCamColumn(): bool
    {
        return ! $this->training->has_attendance && $this->training->isOnline();
    }

    public function array(): array
    {
        $training = $this->training;
        $steps = TrainingStepColumns::stepsOf($training);
        $essay = array_combine($steps, array_map(fn (string $step) => TrainingStepColumns::hasEssay($training, $step), $steps));
        $rows = [];

        $rows[] = ['REKAP HASIL PELATIHAN'];
        $rows[] = ['Topik Pelatihan', $training->title];
        $rows[] = ['Pemateri',        $training->trainer->nama_lengkap ?? '-'];
        $rows[] = ['Tanggal',         Carbon::parse($training->training_date)->format('d-m-Y')];
        $rows[] = ['Waktu',           substr($training->start_time, 0, 5).' - '.substr($training->end_time, 0, 5).' WIB'];
        $rows[] = ['Total Peserta',   $training->participants->count().' orang'];

        $rows[] = $this->headings();

        $no = 1;
        foreach ($training->participants as $participant) {
            $user = $participant->user;

            $rows[] = [
                $no++,
                $user->employee_id ?? '-',
                $user->nama_lengkap ?? '-',
                $user->divisi->name ?? '-',
                $user->no_hp ?? '-',
                ...($this->hasOffCamColumn() ? [$participant->is_off_cam ? ($participant->off_cam_count ?: 1).'x' : '-'] : []),
                ...array_merge([], ...array_map(fn (string $step) => TrainingStepColumns::cells($step, $training, $participant, detailed: true, essay: $essay[$step]), $steps)),
            ];
        }

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                self::restoreDefaultValueBinder();

                $sheet = $event->sheet->getDelegate();
                $headings = $this->headings();
                $totalRows = $this->training->participants->count();
                $lastRow = self::DATA_START + $totalRows - 1;
                $hRow = self::HEADING_ROW;
                $dStart = self::DATA_START;
                $lastCol = Coordinate::stringFromColumnIndex(count($headings));

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

                    // Rata tengah kolom No, status kehadiran, dan nilai
                    foreach ($headings as $index => $heading) {
                        if (in_array($heading, ['No', 'Status Kehadiran'], true) || str_starts_with($heading, 'Nilai ') || str_starts_with($heading, 'Rata-rata ')) {
                            $col = Coordinate::stringFromColumnIndex($index + 1);
                            $sheet->getStyle($col.$dStart.':'.$col.$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                        }
                    }
                }

                // ── Bukti presensi: tautan pendek yang bisa diklik, bukan URL panjang ──
                $proofIndex = array_search('Bukti Screenshot', $headings, true);
                if ($proofIndex !== false && $totalRows > 0) {
                    $col = Coordinate::stringFromColumnIndex($proofIndex + 1);
                    $sheet->getStyle($col.$dStart.':'.$col.$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    for ($row = $dStart; $row <= $lastRow; $row++) {
                        $cell = $sheet->getCell($col.$row);
                        $url = (string) $cell->getValue();

                        if (preg_match('#^https?://#', $url) === 1) {
                            $cell->setValue('Lihat bukti');
                            $cell->getHyperlink()->setUrl($url);
                            $cell->getStyle()->getFont()->setUnderline(true)->getColor()->setARGB('FF0563C1');
                        }
                    }
                }

                // ── Lebar kolom ───────────────────────────────────────────────
                foreach ($headings as $index => $heading) {
                    $width = self::WIDTHS[$heading] ?? (str_starts_with($heading, 'Catatan Essay') ? 28 : self::DEFAULT_WIDTH);
                    $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index + 1))->setWidth($width);
                }
            },
        ];
    }
}
