<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Kotak masuk notifikasi milik user yang sedang login (hasil import/export di belakang layar, dll.).
 * Notifikasi selalu dicari lewat relasi user, jadi notifikasi milik orang lain menghasilkan 404.
 */
class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $notifications = $request->user()->notifications()->paginate(20);

        // Membuka kotak masuk menandai notifikasi yang tampil sebagai sudah dibaca. Penanda "Baru" tetap tampil
        // pada kunjungan ini karena status awalnya sudah dimuat di atas.
        $unreadIds = $notifications->getCollection()->whereNull('read_at')->modelKeys();
        if ($unreadIds !== []) {
            $request->user()->notifications()->whereKey($unreadIds)->update(['read_at' => now()]);
        }

        return view('notification.index', [
            'title' => 'Notifikasi',
            'active' => 'notifications',
            'notifications' => $notifications,
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json(['count' => $request->user()->unreadNotifications()->count()]);
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('success', 'Semua notifikasi ditandai sudah dibaca.');
    }

    public function download(Request $request, string $notification): StreamedResponse|RedirectResponse
    {
        $notification = $this->find($request, $notification);
        $path = $notification->data['download_path'] ?? null;

        if (! $path || ! Storage::disk('local')->exists($path)) {
            return to_route('notifications.index')->with('error', 'File export sudah kedaluwarsa. Silakan export ulang.');
        }

        $notification->markAsRead();

        return Storage::disk('local')->download($path, $notification->data['download_name'] ?? basename($path));
    }

    public function destroy(Request $request, string $notification): RedirectResponse
    {
        $notification = $this->find($request, $notification);

        if ($path = $notification->data['download_path'] ?? null) {
            Storage::disk('local')->deleteDirectory(dirname($path));
        }

        $notification->delete();

        return back()->with('success', 'Notifikasi dihapus.');
    }

    private function find(Request $request, string $id): DatabaseNotification
    {
        return $request->user()->notifications()->findOrFail($id);
    }
}
