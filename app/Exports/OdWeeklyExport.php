<?php

namespace App\Exports;

use App\Exports\Concerns\WritesTextAsText;
use App\Models\OdActivity;
use App\Models\User;
use App\Services\OdReportService;
use App\Support\OdTeam;
use App\Support\OdWeek;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Weekly Report satu bulan dalam format file Weekly Report tim: per minggu baris judul "Posisi | Week N (tanggal)",
 * lalu nama pekerjaan (KAPITAL, urut tanggal) dengan kolom Posisi di-merge per jabatan. Baris kosong antar minggu.
 */
class OdWeeklyExport implements FromArray, WithCustomValueBinder, WithEvents, WithTitle
{
    use WritesTextAsText;

    private const MONTHS = ['JAN', 'FEB', 'MAR', 'APR', 'MEI', 'JUN', 'JUL', 'AGS', 'SEP', 'OKT', 'NOV', 'DES'];

    /** @var list<int> baris judul minggu */
    private array $headerRows = [];

    /** @var list<array{0: int, 1: int}> rentang baris kolom Posisi yang di-merge */
    private array $merges = [];

    /**
     * @param  User  $viewer  user yang mengekspor (Staff hanya datanya sendiri)
     * @param  string|null  $weekKey  filter minggu di layar Weekly (key minggu); di luar bulan ini berarti semua minggu
     * @param  string|null  $result  filter status di layar Weekly
     * @param  string|null  $search  filter nama pekerjaan di layar Weekly
     */
    public function __construct(
        private User $viewer,
        private string $month,
        private ?int $memberId = null,
        private ?string $weekKey = null,
        private ?string $result = null,
        private ?string $search = null,
    ) {}

    public function title(): string
    {
        [$year, $month] = explode('-', $this->month);

        return self::MONTHS[(int) $month - 1].' 1-'.count(OdWeek::inMonth($this->month))." ({$year})";
    }

    /**
     * Minggu yang diekspor: sama dengan pilihan minggu di layar bila key-nya memang termasuk bulan ini,
     * selain itu semua minggu bulan ini (lihat OdReportController::weekly()).
     *
     * @return list<OdWeek>
     */
    private function weeks(): array
    {
        $weeks = OdWeek::inMonth($this->month);

        if ($this->weekKey === null) {
            return $weeks;
        }

        $selected = array_values(array_filter($weeks, fn (OdWeek $week) => $week->key() === $this->weekKey));

        return $selected ?: $weeks;
    }

    public function array(): array
    {
        $weeks = $this->weeks();
        $activities = OdActivity::query()->visibleTo($this->viewer)->reported()
            ->whereDate('date', '>=', $weeks[0]->start->toDateString())
            ->whereDate('date', '<=', end($weeks)->end()->toDateString())
            ->when($this->memberId, fn ($q) => $q->where('user_id', $this->memberId))
            ->when($this->search, fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
            ->with('user.position')
            ->get();

        $rows = [];
        $memberOrder = OdTeam::query()->pluck('id')->flip();
        // Status disaring setelah barisnya digabung, persis seperti layar.
        $byWeek = app(OdReportService::class)->weeklyRows($activities)
            ->when($this->result, fn ($rows) => $rows->where('result', $this->result))
            ->groupBy(fn ($row) => $row->week->key());

        foreach ($weeks as $week) {
            /** @var Collection<int, object> $weekRows */
            $weekRows = $byWeek->get($week->key());
            if (! $weekRows) {
                continue;
            }

            if ($rows) {
                $rows[] = ['', ''];
            }
            $rows[] = ['Posisi', 'Week '.$week->number().' ('.$week->range().')'];
            $this->headerRows[] = count($rows);

            // Per posisi, pekerjaan berurutan dari yang paling awal (seperti template).
            $groups = $weekRows->sortBy(fn ($row) => [$memberOrder[$row->user->id] ?? PHP_INT_MAX, $row->last_date->timestamp, $row->last_id])
                ->groupBy(fn ($row) => OdTeam::positionOf($row->user));

            foreach ($groups as $position => $items) {
                $start = count($rows) + 1;
                foreach ($items as $item) {
                    $rows[] = [$item === $items->first() ? $position : '', mb_strtoupper($item->name)];
                }
                $this->merges[] = [$start, count($rows)];
            }
        }

        return $rows;
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event) {
            $sheet = $event->sheet->getDelegate();
            $sheet->getColumnDimension('A')->setWidth(33.57);
            $sheet->getColumnDimension('B')->setWidth(188.86);
            $sheet->getParent()->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);

            $border = ['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]];
            foreach ($this->headerRows as $row) {
                $sheet->getStyle("A{$row}:B{$row}")->applyFromArray([
                    ...$border,
                    'font' => ['bold' => true],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF4B084']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);
            }
            foreach ($this->merges as [$from, $to]) {
                if ($to > $from) {
                    $sheet->mergeCells("A{$from}:A{$to}");
                }
                $sheet->getStyle("A{$from}:B{$to}")->applyFromArray([...$border, 'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]]);
                $sheet->getStyle("A{$from}:A{$to}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);
            }

            self::restoreDefaultValueBinder();
        }];
    }
}
