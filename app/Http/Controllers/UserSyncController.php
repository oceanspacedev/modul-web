<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\DndUserSyncService;
use App\Services\UserJsonImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

/**
 * Sumber data karyawan dari luar: sinkronisasi DnD (dengan preview) dan Import JSON (Talenta) seperti di DnD.
 */
#[Authorize('import', User::class)]
class UserSyncController extends Controller
{
    private const DIRECTORY = 'dnd-sync';

    public function __construct(private DndUserSyncService $sync) {}

    public function importJson(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'extensions:json,txt', 'max:12288'],
        ]);

        set_time_limit(300);
        $result = UserJsonImportService::importFromContent($request->file('file')->get());

        $message = $result['message'];
        if ($result['created_count'] > 0) {
            $message .= " User baru: {$result['created_count']}.";
        }
        if ($result['error_count'] > 0) {
            $message .= " Gagal: {$result['error_count']} baris.";
        }

        return to_route('user.index')
            ->with($result['success'] && $result['error_count'] === 0 ? 'success' : 'error', $message)
            ->with('importErrors', array_slice([...$result['errors'], ...$result['notices']], 0, 20));
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
        Storage::disk('local')->put(self::DIRECTORY.'/'.$token.'.json', json_encode($snapshot));

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

        $snapshot = $this->snapshot($data['token']);
        if ($snapshot === null) {
            return to_route('user.index')->with('error', 'Sesi sinkronisasi sudah kedaluwarsa, silakan ulangi.');
        }

        set_time_limit(300);

        try {
            $result = $this->sync->apply($snapshot);
        } catch (Throwable $e) {
            report($e);

            return to_route('user.index')->with('error', 'Sinkronisasi DnD gagal: '.$e->getMessage());
        }

        Storage::disk('local')->delete(self::DIRECTORY.'/'.$data['token'].'.json');

        return to_route('user.index')
            ->with('success', "Sinkronisasi DnD selesai. Baru: {$result['created']}, diperbarui: {$result['updated']}, tidak berubah: {$result['unchanged']}.")
            ->with('importErrors', array_slice($result['notices'], 0, 20));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function snapshot(string $token): ?array
    {
        $path = self::DIRECTORY.'/'.$token.'.json';

        if (! Str::isUuid($token) || ! Storage::disk('local')->exists($path)) {
            return null;
        }

        return json_decode(Storage::disk('local')->get($path), true);
    }
}
