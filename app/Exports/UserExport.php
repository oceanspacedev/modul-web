<?php

namespace App\Exports;

use App\Models\User;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Export user dengan kolom yang sama seperti DnD, sehingga hasilnya bisa di-import kembali.
 */
class UserExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection(): Collection
    {
        return User::with(['roles', 'area', 'divisi', 'position', 'approval'])->orderBy('nama_lengkap')->get();
    }

    public function headings(): array
    {
        return [
            'nama_lengkap',
            'username',
            'id_karyawan',
            'no_hp',
            'email',
            'position',
            'role',
            'area',
            'divisi',
            'approval',
        ];
    }

    public function map(mixed $user): array
    {
        return [
            $user->nama_lengkap,
            $user->username,
            $user->employee_id,
            $user->no_hp,
            $user->email,
            $user->position?->name,
            $user->jabatan?->name,
            $user->area?->name,
            $user->divisi?->name,
            $user->approval->nama_lengkap ?? '-',
        ];
    }
}
