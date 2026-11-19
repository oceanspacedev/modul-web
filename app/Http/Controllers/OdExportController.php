<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateExportFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Ekspor Excel OD Report (Weekly, Achievement bulanan, Permintaan Dokumen), dibuat di antrean seperti ekspor lain.
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
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(self::TYPES))],
            'bulan' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'user' => ['nullable', 'integer'],
        ]);

        GenerateExportFile::dispatch($request->user()->getKey(), self::TYPES[$data['type']], null, array_filter([
            'month' => $data['bulan'] ?? today()->format('Y-m'),
            // Staff tetap hanya mendapat datanya sendiri: ekspor memakai hak akses user yang meminta.
            'member_id' => $request->user()->can('manage-od-report') ? ($data['user'] ?? null) : null,
        ]));

        return back()->with('info', GenerateExportFile::QUEUED_MESSAGE);
    }
}
