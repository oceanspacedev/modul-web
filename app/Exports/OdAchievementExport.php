<?php

namespace App\Exports;

use App\Exports\Concerns\WritesTextAsText;
use App\Models\DocumentCategory;
use App\Models\OdDocument;
use App\Models\OdDocumentStatus;
use App\Models\User;
use App\Support\OdTeam;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Monthly Report format "ACHIEVEMENT BUSDEV": header merah NO | POSISI | ACHIEVEMENT | BADAN USAHA | Keterangan,
 * kolom POSISI di-merge per jabatan dan NO mulai ulang per jabatan. Dokumen batal tidak disertakan; status berjalan
 * pertama ditulis "Proses Approval" seperti di template. Urutan: urutan Kategori Dokumen, lalu terlama.
 */
class OdAchievementExport implements FromArray, WithCustomValueBinder, WithEvents, WithTitle
{
    use WritesTextAsText;

    private const MONTHS = ['JAN', 'FEB', 'MAR', 'APRIL', 'MEI', 'JUN', 'JUL', 'AGT', 'SEP', 'OKT', 'NOV', 'DES'];

    /** @var list<array{0: int, 1: int}> */
    private array $groups = [];

    private int $lastRow = 1;

    public function __construct(private User $viewer, private string $month, private ?int $memberId = null) {}

    public function title(): string
    {
        [$year, $month] = explode('-', $this->month);

        return self::MONTHS[(int) $month - 1]."'".$year;
    }

    public function array(): array
    {
        $documents = OdDocument::query()->visibleTo($this->viewer)->where('month', $this->month)
            ->when($this->memberId, fn ($q) => $q->where('pic_user_id', $this->memberId))
            ->whereHas('status', fn ($q) => $q->where('type', '!=', OdDocumentStatus::TYPE_CANCEL))
            ->with(['status', 'pic.position'])
            ->orderBy('submitted_at')->orderBy('id')
            ->get();

        $memberOrder = OdTeam::query()->pluck('id')->flip();
        // Urutan jenis mengikuti urutan Kategori Dokumen.
        $order = DocumentCategory::ordered()->pluck('code')->map(fn ($code) => mb_strtoupper($code))->flip();
        $jenisRank = fn (OdDocument $d) => $order[mb_strtoupper($d->jenis)] ?? $order->count();
        $firstOpen = OdDocumentStatus::firstOfType(OdDocumentStatus::TYPE_OPEN);

        $rows = [['NO', 'POSISI', 'ACHIEVEMENT', 'BADAN USAHA', 'Keterangan']];
        $byPosition = $documents
            ->sortBy(fn (OdDocument $d) => $memberOrder[$d->pic_user_id] ?? PHP_INT_MAX)
            ->groupBy(fn (OdDocument $d) => $d->pic ? OdTeam::positionOf($d->pic) : '-');

        foreach ($byPosition as $position => $items) {
            $start = count($rows) + 1;
            $items = $items->sortBy([[$jenisRank, 'asc'], ['submitted_at', 'asc'], ['id', 'asc']])->values();
            foreach ($items as $index => $document) {
                $rows[] = [
                    $index + 1,
                    $index === 0 ? $position : '',
                    $document->title,
                    $document->badan_usaha === '-' ? '' : $document->badan_usaha,
                    $document->status->is($firstOpen) ? 'Proses Approval' : $document->status->name,
                ];
            }
            $this->groups[] = [$start, count($rows)];
        }

        $this->lastRow = count($rows);

        return $rows;
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event) {
            $sheet = $event->sheet->getDelegate();
            foreach (['A' => 4.71, 'B' => 35.43, 'C' => 129.71, 'D' => 30, 'E' => 32.29] as $column => $width) {
                $sheet->getColumnDimension($column)->setWidth($width);
            }
            $sheet->getParent()->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);

            $sheet->getStyle("A1:E{$this->lastRow}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ]);
            $sheet->getStyle('A1:E1')->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFF0000']],
            ]);
            if ($this->lastRow > 1) {
                $sheet->getStyle("C2:C{$this->lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setWrapText(true);
            }
            foreach ($this->groups as [$from, $to]) {
                if ($to > $from) {
                    $sheet->mergeCells("B{$from}:B{$to}");
                }
                $sheet->getStyle("B{$from}")->getAlignment()->setWrapText(true);
            }

            self::restoreDefaultValueBinder();
        }];
    }
}
