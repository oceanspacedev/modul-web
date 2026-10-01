<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;

class QuizQuestionTemplate implements WithHeadings
{
    use Exportable;
    /**
    * @return \Illuminate\Support\Collection
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
            'seconds'
        ];
    }
}
