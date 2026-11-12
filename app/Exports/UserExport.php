<?php

namespace App\Exports;

use App\Exports\Concerns\WritesTextAsText;
use App\Models\User;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Export user dengan kolom yang sama seperti DnD, sehingga hasilnya bisa di-import kembali.
 */
class UserExport implements FromCollection, WithCustomValueBinder, WithEvents, WithHeadings, WithMapping
{
    use WritesTextAsText;

    /** @var array<string, int>|null jumlah user per nama lengkap (huruf kecil) */
    private ?array $nameCounts = null;

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

    /**
     * Isi kolom approval yang bisa dibaca kembali oleh import tanpa keraguan: nama atasan, atau ID karyawannya bila
     * ada user lain yang bernama sama. Kosong bila tidak punya atasan.
     */
    private function approvalLabel(?User $approval): string
    {
        if (! $approval) {
            return '';
        }

        $this->nameCounts ??= User::query()->toBase()
            ->selectRaw('LOWER(nama_lengkap) as name, COUNT(*) as total')
            ->groupByRaw('LOWER(nama_lengkap)')
            ->pluck('total', 'name')
            ->all();

        $shared = ($this->nameCounts[mb_strtolower((string) $approval->nama_lengkap)] ?? 0) > 1;

        return $shared && filled($approval->employee_id) ? (string) $approval->employee_id : (string) $approval->nama_lengkap;
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
            $this->approvalLabel($user->approval),
        ];
    }
}
