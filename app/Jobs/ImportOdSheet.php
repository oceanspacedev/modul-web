<?php

namespace App\Jobs;

use App\Models\OdSetting;
use App\Models\User;
use App\Notifications\BackgroundTaskFinished;
use App\Services\OdSheetImport;
use RuntimeException;

/**
 * Import Google Sheets ke OD Report (Weekly Report atau Approval Dokumen) di belakang layar. Link dan bulan awal
 * dibaca dari Pengaturan OD saat job berjalan. Satu import per jenis sekaligus, siapa pun yang memulainya.
 */
class ImportOdSheet extends BackgroundTask
{
    public function __construct(int $userId, public string $type)
    {
        parent::__construct($userId);
    }

    public function kind(): string
    {
        return 'od-import-'.$this->type;
    }

    public function uniqueId(): string
    {
        return $this->kind();
    }

    protected function failureTitle(): string
    {
        return 'Import '.OdSheetImport::TYPES[$this->type].' gagal';
    }

    protected function process(): BackgroundTaskFinished
    {
        $import = app(OdSheetImport::class);
        [$spreadsheetId, $gid] = OdSheetImport::parseLink(OdSetting::value(OdSheetImport::linkSetting($this->type)));
        $user = User::find($this->userId);

        if (! $spreadsheetId || ! $user) {
            throw new RuntimeException('Link spreadsheet belum diatur.');
        }

        try {
            $result = $this->type === OdSheetImport::WEEKLY
                ? $import->importWeekly($spreadsheetId, OdSetting::value(OdSetting::IMPORT_WEEKLY_FROM, OdSheetImport::DEFAULT_FROM))
                : $import->importApproval($spreadsheetId, $gid, $user);
        } catch (RuntimeException $e) {
            $import->remember($this->type, false, $e->getMessage());

            throw $e;
        }

        $label = $this->type === OdSheetImport::WEEKLY ? 'pekerjaan' : 'dokumen';
        $count = fn (int $value) => number_format($value, 0, ',', '.');
        $message = "{$count($result['created'])} {$label} baru, {$count($result['updated'])} diperbarui, {$count($result['unchanged'])} tidak berubah.";

        $errors = $result['errors'];
        foreach ($result['unmapped'] ?? [] as $position => $count) {
            $errors[] = "{$count} pekerjaan dilewati: posisi {$position} belum dipetakan ke anggota tim.";
        }
        if ($result['unmapped'] ?? []) {
            $message .= ' '.array_sum($result['unmapped']).' dilewati (posisi belum dipetakan).';
        }
        if ($result['skipped'] ?? 0) {
            $message .= " {$result['skipped']} baris dilewati.";
        }
        if ($this->type === OdSheetImport::WEEKLY) {
            $message .= ' Bulan: '.($result['months'] ? implode(', ', $result['months']) : 'tidak ada').'.';
        }

        $import->remember($this->type, $errors === [], $message);

        return new BackgroundTaskFinished(
            $this->kind(),
            $errors === [] ? BackgroundTaskFinished::STATUS_SUCCESS : BackgroundTaskFinished::STATUS_PARTIAL,
            'Import '.OdSheetImport::TYPES[$this->type].' selesai',
            $message,
            $errors,
        );
    }
}
