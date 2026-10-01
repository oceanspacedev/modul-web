<?php

namespace App\Exports;

use App\Models\Absent;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AbsentExport implements FromCollection, WithHeadings, WithMapping
{
    protected String $date1;
    protected String $date2;

    function __construct(String $date1, String $date2)
    {
        $this->date1 = $date1;
        $this->date2 = $date2;
    }
    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
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

    public function map($row): array
    {
        return [
            $row->user->full_name ?? 'Nonactive users',
            $row->created_at->format('d M Y H:i')
        ];
    }
}
