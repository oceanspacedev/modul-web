<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\Import;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Membatasi import ke sheet pertama. Tanpa ini setiap sheet dalam workbook ikut dibaca, sehingga sheet petunjuk atau
 * catatan di file pengguna bisa terbaca sebagai data.
 */
class FirstSheetOnly implements Import, WithMultipleSheets
{
    public function __construct(private Import $import) {}

    public function sheets(): array
    {
        return [0 => $this->import];
    }
}
