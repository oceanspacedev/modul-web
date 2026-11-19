<?php

namespace App\Http\Controllers;

use App\Models\DocumentRequestRecipient;
use App\Support\WhatsAppNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Nomor WhatsApp pengelola Permintaan Dokumen (di halaman Kategori Dokumen).
 */
class DocumentRequestRecipientController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        // 0812… / +62 812… disimpan sebagai 62812…; isian yang bukan nomor dibiarkan supaya kena pesan "tidak valid".
        $raw = (string) $request->input('whatsapp');
        $request->merge(['whatsapp' => WhatsAppNumber::normalize($raw) ?: $raw]);

        $data = $request->validateWithBag('recipient', [
            'recipient_name' => ['required', 'string', 'max:60'],
            'whatsapp' => ['required', 'regex:/^628\d{8,12}$/', Rule::unique('document_request_recipients', 'whatsapp')],
        ], [
            'whatsapp.regex' => 'Nomor WhatsApp tidak valid. Contoh: 081234567890.',
            'whatsapp.unique' => 'Nomor ini sudah terdaftar.',
        ], ['recipient_name' => 'nama', 'whatsapp' => 'nomor WhatsApp']);

        if (DocumentRequestRecipient::count() >= DocumentRequestRecipient::MAX) {
            return back()->with('error', 'Maksimal '.DocumentRequestRecipient::MAX.' nomor pengelola.');
        }

        DocumentRequestRecipient::create(['name' => $data['recipient_name'], 'whatsapp' => $data['whatsapp']]);

        return back()->with('success', "Nomor {$data['recipient_name']} ditambahkan.");
    }

    public function destroy(DocumentRequestRecipient $recipient): RedirectResponse
    {
        $recipient->delete();

        return back()->with('success', "Nomor {$recipient->name} dihapus.");
    }
}
