<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendHelpdeskWebhook;
use App\Models\DocumentCategory;
use App\Models\DocumentRequest;
use App\Services\Helpdesk\FormSchema;
use App\Services\Helpdesk\TicketIntake;
use App\Services\Helpdesk\TicketSnapshot;
use App\Support\HelpdeskConnection;
use App\Support\OutboundUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API untuk helpdesk (kontrak integrasi helpdesk v1, docs/integrations/README.md di repo helpdesk): helpdesk menjadi
 * pintu depan Permintaan Dokumen. Kategori dan form diambil dari sini, pengajuan dibuat di sini, dan keadaannya dibaca
 * kembali sebagai TicketSnapshot.
 */
class HelpdeskController extends Controller
{
    public function categories(): JsonResponse
    {
        return response()->json([
            'data' => DocumentCategory::active()->ordered()->get()->map(fn (DocumentCategory $category) => [
                'key' => $category->code,
                'name' => $category->name,
                'active' => (bool) $category->is_active,
                // Kartu pilihan kategori di helpdesk (kontrak §3.2 v1.1), sama dengan kartu di form web.
                'description' => (string) ($category->description ?? ''),
                'needs_observation' => (bool) $category->needs_observation,
                'has_template' => $category->hasTemplate(),
            ])->values(),
        ]);
    }

    public function form(string $categoryKey): JsonResponse
    {
        $category = DocumentCategory::active()->where('code', $categoryKey)->first();
        if (! $category) {
            return $this->notFound('Kategori tidak ditemukan atau tidak aktif.');
        }

        return response()->json(['data' => FormSchema::for($category)]);
    }

    public function store(Request $request, TicketIntake $intake): JsonResponse
    {
        // Kiriman ulang dengan kunci yang sama: tiket yang sama, tanpa validasi ulang (tanggal observasi bisa saja
        // sudah lewat saat helpdesk mengulang).
        if ($existing = $intake->existing($request)) {
            return response()->json(['data' => TicketSnapshot::for($existing)], 200);
        }

        $documentRequest = $intake->create($request);

        return response()->json(['data' => TicketSnapshot::for($documentRequest)], $documentRequest->wasRecentlyCreated ? 201 : 200);
    }

    public function show(string $ref): JsonResponse
    {
        // Pengajuan yang sudah dihapus tetap terbaca (status cancelled/"Dihapus"), supaya "Muat ulang" di helpdesk bisa
        // memulihkan pembatalan bila webhook terakhirnya gagal terkirim.
        $documentRequest = DocumentRequest::withTrashed()->where('code', $ref)->where('source', DocumentRequest::SOURCE_HELPDESK)->first();
        if (! $documentRequest) {
            return $this->notFound('Tiket tidak ditemukan.');
        }

        return response()->json(['data' => TicketSnapshot::for($documentRequest)]);
    }

    /**
     * PUT {base}/webhook (kontrak §4.1): helpdesk mendaftarkan URL dan rahasia webhook-nya sendiri setelah kode koneksi
     * dipakai, jadi admin tidak perlu menyalinnya ke .env. Pendaftaran berikutnya menimpa yang lama.
     */
    public function registerWebhook(Request $request): JsonResponse
    {
        $data = $request->validate([
            'url' => ['required', 'string', 'max:500', 'url:http,https', function (string $attribute, mixed $value, \Closure $fail): void {
                if (is_string($value) && ($problem = OutboundUrl::problem($value)) !== null) {
                    $fail($problem);
                }
            }],
            'secret' => ['required', 'string', 'min:16', 'max:500'],
        ]);

        HelpdeskConnection::registerWebhook($data['url'], $data['secret']);

        return response()->json(['data' => ['registered' => true, 'url' => $data['url']]]);
    }

    /**
     * POST {base}/webhook/test (kontrak §4.2): kirim event "ping" ke webhook helpdesk sekarang juga dan laporkan
     * hasilnya, supaya helpdesk bisa membuktikan arah sistem ini → helpdesk tanpa membuat tiket.
     */
    public function testWebhook(): JsonResponse
    {
        return response()->json(['data' => SendHelpdeskWebhook::ping()]);
    }

    private function notFound(string $message): JsonResponse
    {
        return response()->json(['message' => $message], 404);
    }
}
