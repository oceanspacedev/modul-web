<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessDndSync;
use App\Jobs\ProcessUserJsonImport;
use App\Models\User;
use App\Services\DndUserSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

/**
 * Sumber data karyawan dari luar: sinkronisasi DnD (dengan preview) dan Import JSON (Talenta) seperti di DnD.
 * Langkah yang berat (import dan menerapkan sinkronisasi) diproses di queue; hasilnya dikirim lewat notifikasi.
 */
#[Authorize('import', User::class)]
class UserSyncController extends Controller
{
    public function __construct(private DndUserSyncService $sync) {}

    public function importJson(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'extensions:json,txt', 'max:12288'],
        ]);

        $path = $request->file('file')->storeAs('imports', Str::uuid().'.json', 'local');

        ProcessUserJsonImport::dispatch($request->user()->id, $path);

        return to_route('user.index')->with('info', ProcessUserJsonImport::QUEUED_MESSAGE);
    }

    public function preview(): RedirectResponse
    {
        try {
            $snapshot = $this->sync->fetch();
        } catch (Throwable $e) {
            report($e);

            return to_route('user.index')->with('error', 'Sinkronisasi DnD gagal: '.$e->getMessage());
        }

        $token = (string) Str::uuid();
        Storage::disk('local')->put(ProcessDndSync::snapshotPath($token), json_encode($snapshot));

        return to_route('user.dnd.show', $token);
    }

    public function show(string $token): View|RedirectResponse
    {
        $snapshot = $this->snapshot($token);
        if ($snapshot === null) {
            return to_route('user.index')->with('error', 'Sesi sinkronisasi sudah kedaluwarsa, silakan ulangi.');
        }

        return view('setting.user.dnd-preview', [
            'title' => 'Sinkronisasi DnD',
            'active' => 'setting',
            'token' => $token,
            'total' => count($snapshot['users']),
            'plan' => $this->sync->plan($snapshot),
        ]);
    }

    public function process(Request $request): RedirectResponse
    {
        $data = $request->validate(['token' => ['required', 'uuid']]);

        if ($this->snapshot($data['token']) === null) {
            return to_route('user.index')->with('error', 'Sesi sinkronisasi sudah kedaluwarsa, silakan ulangi.');
        }

        ProcessDndSync::dispatch($request->user()->id, $data['token']);

        return to_route('user.index')->with('info', 'Sinkronisasi DnD sedang diproses di belakang layar. Hasilnya akan dikirim ke menu Notifikasi, WhatsApp, dan email Anda.');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function snapshot(string $token): ?array
    {
        $path = ProcessDndSync::snapshotPath($token);

        if (! Str::isUuid($token) || ! Storage::disk('local')->exists($path)) {
            return null;
        }

        return json_decode(Storage::disk('local')->get($path), true);
    }
}
