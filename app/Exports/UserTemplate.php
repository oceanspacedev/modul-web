<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;

class UserTemplate implements Export, WithHeadings
{
    use Exportable;

    /**
     * @return Collection
     */
    public function headings(): array
    {
        return [
            'id_karyawan',
            'full_name',
            'username',
            'email',
            'no_wa',
            'password',
            'divisi',
            'sub_divisi',
            'job_level',
        ];
    }
}
