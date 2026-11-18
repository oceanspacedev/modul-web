<?php

namespace App\Http\Controllers;

use App\Models\OdDocument;
use App\Models\OdDocumentStatus;
use App\Models\OdPicDefault;
use App\Support\OdTeam;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Pengaturan OD Report (Leader): daftar status dokumen dan PIC bawaan per jenis dokumen.
 */
class OdSettingController extends Controller
{
    public function index(): View
    {
        return view('od-report.settings', [
            'title' => 'Pengaturan OD Report',
            'active' => 'od-settings',
            'statuses' => OdDocumentStatus::ordered()->withCount('documents')->get(),
            'team' => OdTeam::members(),
            'picDefaults' => OdPicDefault::pluck('user_id', 'jenis'),
        ]);
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

    public function updatePics(Request $request): RedirectResponse
    {
        $memberIds = OdTeam::query()->pluck('id')->all();
        $data = $request->validate([
            'pic' => ['array'],
            'pic.*' => ['nullable', 'integer', Rule::in($memberIds)],
        ]);

        foreach (OdDocument::JENIS as $jenis) {
            $userId = $data['pic'][$jenis] ?? null;
            $userId
                ? OdPicDefault::updateOrCreate(['jenis' => $jenis], ['user_id' => $userId])
                : OdPicDefault::where('jenis', $jenis)->delete();
        }

        return back()->with('success', 'PIC bawaan disimpan.');
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
