<?php

namespace App\Http\Controllers;

use App\Support\HelpdeskConnection;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Pengaturan Helpdesk (admin): membuat kode koneksi yang ditempel admin helpdesk di "Hubungkan sistem". Menggantikan
 * HELPDESK_API_TOKEN / HELPDESK_WEBHOOK_URL / HELPDESK_WEBHOOK_SECRET di .env, yang tetap dipakai sebagai cadangan.
 */
class HelpdeskSettingController extends Controller
{
    public function index(): View
    {
        return view('helpdesk-settings.index', [
            'title' => 'Pengaturan Helpdesk',
            'active' => 'helpdesk-settings',
            'apiBaseUrl' => HelpdeskConnection::apiBaseUrl(),
            'tokenSource' => HelpdeskConnection::tokenSource(),
            'tokenIssuedAt' => HelpdeskConnection::tokenIssuedAt(),
            'previousTokenValidUntil' => HelpdeskConnection::previousTokenValidUntil(),
            'webhookSource' => HelpdeskConnection::webhookSource(),
            'webhookUrl' => HelpdeskConnection::webhookUrl(),
            'webhookRegisteredAt' => HelpdeskConnection::webhookRegisteredAt(),
            'lastDelivery' => HelpdeskConnection::lastDelivery(),
            // Kode hanya ada di flash sesi satu kali setelah dibuat; tidak bisa dilihat ulang.
            'code' => session('helpdesk_connection_code'),
        ]);
    }

    public function issue(): RedirectResponse
    {
        $code = HelpdeskConnection::issueCode();

        return redirect()->route('helpdesk-settings.index')
            ->with('helpdesk_connection_code', $code)
            ->with('success', 'Kode koneksi dibuat. Salin sekarang: kode ini hanya ditampilkan sekali.');
    }

    public function disconnect(): RedirectResponse
    {
        HelpdeskConnection::disconnect();

        return redirect()->route('helpdesk-settings.index')
            ->with('success', 'Koneksi helpdesk diputus. Helpdesk tidak bisa lagi memanggil API sampai kode baru dipakai.');
    }
}
