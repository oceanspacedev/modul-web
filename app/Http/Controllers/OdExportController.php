<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateExportFile;
use App\Models\OdActivity;
use App\Support\OdTeam;
use App\Support\OdWeek;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Ekspor Excel OD Report (Weekly, Achievement bulanan, Permintaan Dokumen), dibuat di antrean seperti ekspor lain.
 * Filter yang sedang aktif di layar ikut dikirim supaya isi file sama dengan yang terlihat.
 */
class OdExportController extends Controller
{
    private const TYPES = [
        'weekly' => GenerateExportFile::OD_WEEKLY,
        'monthly' => GenerateExportFile::OD_ACHIEVEMENT,
        'requests' => GenerateExportFile::DOCUMENT_REQUESTS,
    ];

    public function __invoke(Request $request): RedirectResponse
    {
        $canPickMember = $request->user()->can('manage-od-report');

        $rules = [
            'type' => ['required', Rule::in(array_keys(self::TYPES))],
            'bulan' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'minggu' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', Rule::in(OdActivity::RESULTS)],
            'search' => ['nullable', 'string'],
            'user' => ['nullable', 'integer'],
        ];

        // Sama seperti layar report (OdReportController::monthAndMember): hanya id anggota tim OD yang boleh dipakai.
        if ($canPickMember) {
            $rules['user'][] = Rule::in(OdTeam::query()->pluck('id')->all());
        }

        $data = $request->validate($rules, ['user.in' => 'Anggota yang diekspor harus anggota tim OD.']);

        GenerateExportFile::dispatch($request->user()->getKey(), self::TYPES[$data['type']], null, array_filter([
            // Default bulan = bulan minggu laporan, sama dengan default layar Weekly/Monthly.
            'month' => $data['bulan'] ?? OdWeek::of(today())->month(),
            'week' => $data['minggu'] ?? null,
            'status' => $data['status'] ?? null,
            'search' => $data['search'] ?? null,
            // Staff tetap hanya mendapat datanya sendiri: ekspor memakai hak akses user yang meminta.
            'member_id' => $canPickMember ? ($data['user'] ?? null) : null,
        ]));

        return back()->with('info', GenerateExportFile::QUEUED_MESSAGE);
    }
}
