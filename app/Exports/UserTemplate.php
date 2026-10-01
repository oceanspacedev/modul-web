<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;

class UserTemplate implements WithHeadings
{
    use Exportable;
    /**
     * @return \Illuminate\Support\Collection
     */
    public function headings(): array
    {
        return [
            'full_name',
            'username',
            'password',
            'divisi',
            'sub_divisi',
            'job_level',
        ];
    }
}
