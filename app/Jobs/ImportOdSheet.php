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
        $number = fn (int $value) => number_format($value, 0, ',', '.');
        $parts = ["{$number($result['created'])} {$label} baru", "{$number($result['updated'])} diperbarui"];

        $errors = $result['errors'];
        foreach ($result['unmapped'] ?? [] as $position => $count) {
            $errors[] = "{$count} pekerjaan dilewati: posisi {$position} belum ada orangnya.";
        }
        $skipped = array_sum($result['unmapped'] ?? []) + ($result['skipped'] ?? 0);
        if ($skipped) {
            $parts[] = "{$number($skipped)} dilewati";
        }
        if ($this->type === OdSheetImport::WEEKLY) {
            $months = $result['months'];
            $parts[] = match (count($months)) {
                0 => 'tab bulan tidak ditemukan',
                1 => $months[0],
                default => $months[0].' – '.end($months),
            };
        }
        $message = implode(' · ', $parts);

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
