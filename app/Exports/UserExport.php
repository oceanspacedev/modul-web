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
            'id_karyawan',
            'nama',
            'username',
            'email',
            'no_wa',
            'job_level',
            'divisi',
            'sub_divisi',
        ];
    }

    public function map($row): array
    {
        return [
            $row->id_karyawan ?? '-',
            $row->full_name,
            $row->username,
            $row->email ?? '-',
            $row->no_wa ?? '-',
            $row->joblevel->name,
            $row->divisi->name,
            $row->subdivisi->name ?? '-',
        ];
    }
}
