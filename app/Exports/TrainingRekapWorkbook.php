<?php

namespace App\Exports;

use App\Models\Training;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Rekap pelatihan yang memakai form evaluasi: sheet rekap seperti biasa ditambah sheet jawaban evaluasi.
 */
class TrainingRekapWorkbook implements Export, WithMultipleSheets
{
    public function __construct(protected Training $training) {}

    public function sheets(): array
    {
        return [
            new TrainingRekapExport($this->training),
            new TrainingEvaluationSheet($this->training),
        ];
    }
}
