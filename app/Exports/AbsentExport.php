<?php

namespace App\Exports;

use App\Models\Absent;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AbsentExport implements FromCollection, WithHeadings, WithMapping
{
    protected string $date1;

    protected string $date2;

    public function __construct(string $date1, string $date2)
    {
        $this->date1 = $date1;
        $this->date2 = $date2;
    }

    public function collection(): Collection
    {
        $date1 = $this->date1;
        $date2 = $this->date2;

        return Absent::with('user')
            ->whereBetween('created_at', [$date1, $date2])
            ->orderBy('created_at')
            ->get();
    }

    public function headings(): array
    {
        return [
            'nama',
            'tanggal',
        ];
    }

    public function map(mixed $row): array
    {
        return [
            $row->user->full_name ?? 'Nonactive users',
            $row->created_at->format('d M Y H:i'),
        ];
    }
}
