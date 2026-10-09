<?php

namespace App\Exports;

use App\Models\User;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class UserExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection(): Collection
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

    public function map(mixed $row): array
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
