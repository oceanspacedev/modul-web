<?php

namespace App\Exports;

use App\Models\LearningModule;
use App\Support\LearningModuleProgress;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Sheet 1 rekap per peserta, sheet 2 riwayat semua percobaan pretest/posttest.
 */
class LearningModuleRekapExport implements Export, WithMultipleSheets
{
    public function __construct(protected LearningModule $module) {}

    public function sheets(): array
    {
        $this->module->load(['items.materialable', 'divisis', 'roles']);
        $participants = LearningModuleProgress::forParticipants($this->module);

        return [
            new LearningModuleRekapSheet($this->module, $participants),
            new LearningModuleAttemptsSheet($participants),
        ];
    }
}
