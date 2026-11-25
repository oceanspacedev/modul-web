<?php

namespace App\Jobs;

use App\Models\DocumentRequest;
use App\Models\OdDocument;
use App\Services\Helpdesk\TicketSnapshot;
use App\Support\HelpdeskConnection;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Webhook ticket.updated ke helpdesk (kontrak integrasi §4) untuk Permintaan Dokumen yang berasal dari helpdesk.
 * Snapshot disusun saat job berjalan, jadi beberapa perubahan beruntun cukup satu kiriman (job unik per pengajuan
 * selama masih mengantre). Ditandatangani HMAC-SHA256 dan diulang dengan jeda bila helpdesk tidak menjawab 2xx.
 *
 * Kiriman untuk pengajuan yang sama tidak pernah berjalan bersamaan (WithoutOverlapping): updated_at snapshot hanya
 * berpresisi detik dan helpdesk menerima snapshot dengan updated_at yang sama, jadi dua job yang berjalan paralel bisa
 * tiba terbalik dan snapshot lama menimpa yang baru. Dengan antrean per pengajuan, job berikutnya menyusun snapshot
 * setelah kiriman sebelumnya selesai, sehingga selalu yang terbaru.
 */
class SendHelpdeskWebhook implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /** Batas percobaan termasuk saat dilepas kembali karena kiriman lain untuk pengajuan yang sama masih berjalan. */
    public int $tries = 30;

    /** Batas kegagalan kiriman sebenarnya (helpdesk tidak menjawab 2xx / tidak terjangkau). */
    public int $maxExceptions = 6;

    public int $timeout = 30;

    /** Kunci unik dilepas otomatis bila job tertahan terlalu lama. */
    public int $uniqueFor = 300;

    public string $eventId;

    public function __construct(public int $documentRequestId)
    {
        // Satu event_id untuk semua percobaan ulang kiriman yang sama.
        $this->eventId = (string) Str::uuid();
    }

    /**
     * Kirim (setelah transaksi selesai) bila pengajuan warisan memakai nomor tiket helpdesk dan webhook sudah diatur.
     */
    public static function forRequest(?DocumentRequest $request): void
    {
        if (! $request?->isFromHelpdesk() || blank($request->helpdesk_ticket_number) || ! self::configured()) {
            return;
        }

        $id = (int) $request->getKey();

        // Antrean sync (lokal/dev): kegagalan helpdesk tidak boleh menggagalkan aksi tim OD yang sudah tersimpan.
        // Kegagalannya tetap tercatat (lastDelivery) dan dilaporkan; di antrean sungguhan job diulang dengan jeda.
        if (config('queue.connections.'.config('queue.default').'.driver') === 'sync') {
            DB::afterCommit(function () use ($id): void {
                try {
                    self::dispatchSync($id);
                } catch (Throwable $exception) {
                    report($exception);
                }
            });

            return;
        }

        self::dispatch($id)->afterCommit();
    }

    public static function forDocument(OdDocument $document): void
    {
        if ($document->isFromRequest()) {
            self::forRequest($document->documentRequest()->withTrashed()->first());
        }
    }

    public static function configured(): bool
    {
        return HelpdeskConnection::webhookConfigured();
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 30, 60, 300, 900];
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->uniqueId()))->releaseAfter(5)->expireAfter($this->timeout + 30)];
    }

    public function uniqueId(): string
    {
        return (string) $this->documentRequestId;
    }

    public function handle(): void
    {
        if (! self::configured()) {
            return;
        }

        $request = DocumentRequest::withTrashed()->find($this->documentRequestId);
        if (! $request?->isFromHelpdesk() || blank($request->helpdesk_ticket_number)) {
            return;
        }

        $body = json_encode([
            'event_id' => $this->eventId,
            'type' => 'ticket.updated',
            'occurred_at' => now()->toIso8601String(),
            'data' => TicketSnapshot::for($request),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $timestamp = (string) now()->getTimestamp();

        try {
            Http::withHeaders([
                'X-Helpdesk-Timestamp' => $timestamp,
                'X-Helpdesk-Signature' => self::signature($timestamp, $body),
                'Accept' => 'application/json',
            ])
                ->withBody($body, 'application/json')
                ->timeout(15)
                ->withoutRedirecting()
                ->post((string) HelpdeskConnection::webhookUrl())
                ->throw();
        } catch (Throwable $exception) {
            HelpdeskConnection::recordDelivery(match (true) {
                $exception instanceof RequestException => 'Helpdesk membalas HTTP '.$exception->response->status().($exception->response->status() === 401 ? ' (rahasia webhook tidak cocok).' : '.'),
                $exception instanceof ConnectionException => 'Helpdesk tidak dapat dihubungi.',
                default => class_basename($exception).': '.$exception->getMessage(),
            });

            throw $exception;
        }

        HelpdeskConnection::recordDelivery(null);
    }

    /**
     * Kirim event "ping" bertanda tangan ke webhook helpdesk (kontrak §4.2), dipakai tes menyeluruh dari helpdesk.
     * Langsung dalam request, tanpa antrean dan tanpa pengulangan: hasilnya dikembalikan apa adanya.
     *
     * @return array{delivered: bool, event_id: string, status: ?int, error: ?string}
     */
    public static function ping(): array
    {
        $eventId = (string) Str::uuid();
        if (! self::configured()) {
            return ['delivered' => false, 'event_id' => $eventId, 'status' => null, 'error' => 'Webhook helpdesk belum terdaftar.'];
        }

        // "data" berupa objek kosong: helpdesk mewajibkan field itu ada, dan tipe selain ticket.updated diabaikan.
        $body = json_encode(['event_id' => $eventId, 'type' => 'ping', 'occurred_at' => now()->toIso8601String(), 'data' => ['ping' => true]], JSON_THROW_ON_ERROR);
        $timestamp = (string) now()->getTimestamp();

        try {
            $response = Http::withHeaders([
                'X-Helpdesk-Timestamp' => $timestamp,
                'X-Helpdesk-Signature' => self::signature($timestamp, $body),
                'Accept' => 'application/json',
            ])->withBody($body, 'application/json')->timeout(10)->withoutRedirecting()->post((string) HelpdeskConnection::webhookUrl());
        } catch (ConnectionException) {
            HelpdeskConnection::recordDelivery('Helpdesk tidak dapat dihubungi.');

            return ['delivered' => false, 'event_id' => $eventId, 'status' => null, 'error' => 'Helpdesk tidak dapat dihubungi dari sistem ini.'];
        }

        $error = match (true) {
            $response->successful() => null,
            $response->status() === 401 => 'Helpdesk menolak tanda tangan (rahasia webhook tidak cocok).',
            default => 'Helpdesk membalas HTTP '.$response->status().'.',
        };
        HelpdeskConnection::recordDelivery($error);

        return ['delivered' => $error === null, 'event_id' => $eventId, 'status' => $response->status(), 'error' => $error];
    }

    public static function signature(string $timestamp, string $body): string
    {
        return 'sha256='.hash_hmac('sha256', "{$timestamp}.{$body}", (string) HelpdeskConnection::webhookSecret());
    }
}
