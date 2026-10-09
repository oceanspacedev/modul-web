<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;

class QuizQuestionTemplate implements Export, WithHeadings
{
    use Exportable;

    /**
     * @return Collection
     */
    public function headings(): array
    {
        return [
            'question',
            'true_option',
            'option_a',
            'option_b',
            'option_c',
            'option_d',
            'seconds',
        ];
    }
}
