<?php

namespace App\Exports;

use Carbon\CarbonImmutable;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Rekap nilai gabungan banyak pelatihan dan modul (pretest/posttest, termasuk kuis video/dokumen) dalam satu file.
 *
 * Bagian yang ikut ditentukan pemanggil sesuai hak akses pengguna yang meminta export, karena export dibuat di queue
 * tanpa user yang login.
 */
class ScoreRecapExport implements Export, WithMultipleSheets
{
    public const SECTION_TRAININGS = 'trainings';

    /** Kode jenis modul pretest/posttest, sama dengan LearningModule::routeContext(). */
    public const MODULE_TYPES = ['standalone', 'video', 'document'];

    /**
     * @param  list<string>  $sections  'trainings' dan/atau jenis modul dari MODULE_TYPES
     */
    public function __construct(
        protected CarbonImmutable $from,
        protected CarbonImmutable $to,
        protected array $sections,
        protected ?int $divisiId = null,
    ) {}

    public function sheets(): array
    {
        $sheets = [];
        $from = $this->from->startOfDay();
        $to = $this->to->endOfDay();

        if (in_array(self::SECTION_TRAININGS, $this->sections, true)) {
            $sheets[] = new TrainingScoresSheet($from, $to, $this->divisiId);
        }

        $moduleTypes = array_values(array_intersect(self::MODULE_TYPES, $this->sections));
        if ($moduleTypes !== []) {
            $sheets[] = new LearningModuleScoresSheet($from, $to, $moduleTypes, $this->divisiId);
        }

        return $sheets;
    }
}
