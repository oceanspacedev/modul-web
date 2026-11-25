<?php

namespace App\Http\Controllers;

use App\Jobs\ImportOdSheet;
use App\Models\OdActivity;
use App\Models\OdDocumentStatus;
use App\Models\OdSetting;
use App\Services\OdSheetImport;
use App\Services\OdSheetSync;
use App\Support\OdTeam;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Pengaturan OD Report (Leader): daftar status dokumen, sinkron Google Sheets, dan import dari Google Sheets. Jenis,
 * tahapan dan PIC bawaan diatur di Kategori Dokumen.
 */
class OdSettingController extends Controller
{
    public function index(OdSheetSync $sheet, OdSheetImport $import): View
    {
        return view('od-report.settings', [
            'title' => 'Pengaturan',
            'active' => 'od-settings',
            // "Dipakai" termasuk dokumen yang diarsipkan: sama dengan aturan penolakan hapus di bawah.
            'statuses' => OdDocumentStatus::ordered()->withCount(['documents' => fn ($q) => $q->withTrashed()])->get(),
            'sheet' => [
                'enabled' => $sheet->enabled(),
                'link' => OdSetting::value(OdSetting::SHEET_LINK),
                'url' => OdSetting::value(OdSetting::SHEET_URL),
                'from' => OdSetting::value(OdSetting::SHEET_FROM),
                'open' => $sheet->spreadsheetUrl(),
                'pending' => $sheet->enabled() ? $sheet->pending()->count() : 0,
                'last' => $sheet->status(),
            ],
            'import' => [
                'weekly' => OdSetting::value(OdSetting::IMPORT_WEEKLY_LINK),
                'from' => OdSetting::value(OdSetting::IMPORT_WEEKLY_FROM, OdSheetImport::DEFAULT_FROM),
                'approval' => OdSetting::value(OdSetting::IMPORT_APPROVAL_LINK),
                'positions' => OdSheetImport::positionMap(),
                'members' => $members = OdTeam::members(),
                'auto' => collect(OdSheetImport::positionMap())->map(fn ($userId, $position) => OdSheetImport::autoMatch($position, $members)),
                'last' => collect(OdSheetImport::TYPES)->map(fn ($label, $type) => $import->status($type)),
            ],
        ]);
    }

    /**
     * Simpan link (untuk Weekly juga bulan awal dan pemetaan POSISI), lalu jalankan import di belakang layar. Aman
     * diulang: baris yang sudah pernah di-import diperbarui, bukan digandakan.
     */
    public function importSheet(Request $request, string $type): RedirectResponse
    {
        $data = $request->validateWithBag("import-{$type}", [
            'link' => ['required', 'string', 'max:300', function (string $attribute, mixed $value, \Closure $fail) {
                if (! OdSheetSync::parseSpreadsheetId($value)) {
                    $fail('Link spreadsheet tidak valid. Salin dari kolom alamat browser.');
                }
            }],
            'from' => [Rule::requiredIf($type === OdSheetImport::WEEKLY), 'nullable', 'date_format:Y-m'],
            'positions' => ['nullable', 'array'],
            'positions.*' => ['nullable', 'integer', Rule::in(OdTeam::query()->pluck('id')->all())],
        ], [
            'positions.*.in' => 'Pilih anggota tim OD.',
        ], [
            'link' => 'link spreadsheet',
            'from' => 'bulan awal',
        ]);

        OdSetting::put([OdSheetImport::linkSetting($type) => trim($data['link'])]);

        if ($type === OdSheetImport::WEEKLY) {
            OdSetting::put([OdSetting::IMPORT_WEEKLY_FROM => $data['from']]);
            $map = OdSheetImport::positionMap();
            foreach ($data['positions'] ?? [] as $position => $userId) {
                if (array_key_exists($position, $map)) {
                    $map[$position] = $userId ? (int) $userId : null;
                }
            }
            OdSheetImport::savePositionMap($map);
        }

        ImportOdSheet::dispatch($request->user()->getKey(), $type);

        return back()->with('success', 'Import '.OdSheetImport::TYPES[$type].' berjalan di belakang layar. Hasilnya dikirim ke menu Notifikasi.');
    }

    /**
     * Simpan link spreadsheet + URL Web App, lalu cek koneksinya. Ganti spreadsheet = semua pekerjaan dianggap belum
     * terkirim ke spreadsheet baru (mulai dari tanggal "Kirim mulai").
     */
    public function updateSheet(Request $request, OdSheetSync $sheet): RedirectResponse
    {
        $data = $request->validate([
            'sheet_link' => ['nullable', 'string', 'max:300', 'required_with:sheet_url', function (string $attribute, mixed $value, \Closure $fail) {
                if (! OdSheetSync::parseSpreadsheetId($value)) {
                    $fail('Link spreadsheet tidak valid. Salin dari kolom alamat browser.');
                }
            }],
            'sheet_url' => ['nullable', 'string', 'max:300', 'required_with:sheet_link', 'regex:'.OdSheetSync::WEB_APP_PATTERN],
            'sheet_from' => ['nullable', 'date'],
        ], [
            'sheet_url.regex' => 'URL Web App harus dari script.google.com dan berakhiran /exec.',
        ], [
            'sheet_link' => 'link spreadsheet',
            'sheet_url' => 'URL Web App',
            'sheet_from' => 'tanggal mulai',
        ]);

        $newId = OdSheetSync::parseSpreadsheetId($data['sheet_link'] ?? null);
        if ($newId !== $sheet->spreadsheetId()) {
            // Lewat query agar tidak memicu event saved.
            OdActivity::whereNotNull('sheet_hash')->update(['sheet_hash' => null]);
        }

        OdSetting::put([
            OdSetting::SHEET_LINK => $newId ? "https://docs.google.com/spreadsheets/d/{$newId}/edit" : null,
            OdSetting::SHEET_URL => $data['sheet_url'] ?? null,
            OdSetting::SHEET_FROM => $data['sheet_from'] ?? null,
        ]);

        if (! $sheet->enabled()) {
            return back()->with('success', 'Sinkron Google Sheets dimatikan.');
        }

        try {
            $name = $sheet->ping($sheet->webAppUrl(), $newId);
        } catch (\RuntimeException $e) {
            return back()->with('error', 'Tersimpan, tapi belum tersambung: '.$e->getMessage());
        }

        OdSheetSync::queue();

        return back()->with('success', 'Tersimpan dan tersambung ke spreadsheet "'.$name.'".');
    }

    /** "Sinkron Sekarang": kirim pekerjaan yang belum tertulis ke spreadsheet Weekly Report. */
    public function syncSheet(OdSheetSync $sheet): RedirectResponse
    {
        if (! $sheet->enabled()) {
            return back()->with('error', 'Sinkron Google Sheets belum diatur.');
        }

        OdSheetSync::queue();

        return back()->with('success', 'Sinkron ke spreadsheet dijalankan.');
    }

    public function storeStatus(Request $request): RedirectResponse
    {
        $data = $request->validate($this->statusRules());

        OdDocumentStatus::create([...$data, 'sort' => (int) OdDocumentStatus::max('sort') + 1]);

        return back()->with('success', "Status {$data['name']} ditambahkan.");
    }

    public function updateStatus(Request $request, OdDocumentStatus $status): RedirectResponse
    {
        $data = $request->validate($this->statusRules($status));

        if ($data['type'] !== $status->type && $this->isLastOfType($status)) {
            return back()->with('error', 'Minimal harus ada satu status berjalan dan satu status selesai.');
        }

        // Nama ada di satu tempat; dokumen yang memakai status ini otomatis ikut berganti nama.
        $status->update($data);

        return back()->with('success', 'Status diperbarui.');
    }

    public function destroyStatus(OdDocumentStatus $status): RedirectResponse
    {
        if ($status->documents()->withTrashed()->exists()) {
            return back()->with('error', "Status {$status->name} masih dipakai dokumen.");
        }
        if ($this->isLastOfType($status)) {
            return back()->with('error', 'Minimal harus ada satu status berjalan dan satu status selesai.');
        }

        $status->delete();

        return back()->with('success', 'Status dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function statusRules(?OdDocumentStatus $status = null): array
    {
        return [
            'name' => ['required', 'string', 'max:30', Rule::unique('od_document_statuses', 'name')->ignore($status)],
            'type' => ['required', Rule::in(array_keys(OdDocumentStatus::TYPES))],
        ];
    }

    private function isLastOfType(OdDocumentStatus $status): bool
    {
        return in_array($status->type, [OdDocumentStatus::TYPE_OPEN, OdDocumentStatus::TYPE_DONE], true)
            && OdDocumentStatus::where('type', $status->type)->count() === 1;
    }
}
