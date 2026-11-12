<?php

namespace App\Exports\Concerns;

use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

/**
 * Menulis setiap teks apa adanya sebagai teks. Tanpa ini Excel menebak isinya: No. HP "0812..." dan ID karyawan
 * "25.10" berubah menjadi angka (nol di depan hilang, "25.10" menjadi 25.1), dan teks berawalan "=" dijalankan sebagai
 * rumus. Hanya nilai yang memang angka di PHP (int/float) yang ditulis sebagai angka, jadi nilai kuis harus di-cast
 * ke float oleh pembuat barisnya.
 *
 * Dipakai bersama `implements WithCustomValueBinder, WithEvents`. Class yang punya registerEvents() sendiri harus
 * memanggil self::restoreDefaultValueBinder() di AfterSheet-nya.
 */
trait WritesTextAsText
{
    public function bindValue(Cell $cell, mixed $value): bool
    {
        if (is_string($value)) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        return (new DefaultValueBinder)->bindValue($cell, $value);
    }

    /**
     * Pengikat nilai berlaku global di PhpSpreadsheet, jadi dikembalikan ke bawaan begitu sheet ini selesai agar
     * export dan import lain di proses yang sama (worker antrean) tidak ikut terpengaruh.
     */
    public function registerEvents(): array
    {
        return [AfterSheet::class => fn () => self::restoreDefaultValueBinder()];
    }

    public static function restoreDefaultValueBinder(): void
    {
        Cell::setValueBinder(app(config('excel.value_binder.default', DefaultValueBinder::class)));
    }
}
