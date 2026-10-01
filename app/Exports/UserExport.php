<?php

namespace App\Exports;

use App\Models\User;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class UserExport implements FromCollection, WithHeadings, WithMapping
{
    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return User::with(['joblevel', 'divisi', 'subdivisi'])->orderBy('full_name')->get();
    }

    public function headings(): array
    {
        return [
            'nama',
            'username',
            'job_level',
            'divisi',
            'sub_divisi',
        ];
    }

    public function map($row): array
    {
        return [
            $row->full_name,
            $row->username,
            $row->joblevel->name,
            $row->divisi->name,
            $row->subdivisi->name ?? '-',
        ];
    }
}
