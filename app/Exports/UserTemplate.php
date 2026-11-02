<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Template import user, kolomnya sama dengan template DnD.
 */
class UserTemplate implements FromCollection, WithHeadings
{
    public function collection(): Collection
    {
        return collect([
            [
                'nama_lengkap' => 'KUSUMA CHANDRA',
                'username' => 'kusumachandra',
                'id_karyawan' => '25.02.001',
                'no_hp' => '081234567890',
                'email' => 'kusuma.chandra@example.com',
                'role' => 'MANAGER',
                'area' => 'ULS',
                'divisi' => 'RETAIL',
                'position' => 'ULS - GENERAL MANAGER',
                'approval' => '',
                'password' => '',
            ],
        ]);
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'nama_lengkap',
            'username',
            'id_karyawan',
            'no_hp',
            'email',
            'role',
            'area',
            'divisi',
            'position',
            'approval',
            'password',
        ];
    }
}
