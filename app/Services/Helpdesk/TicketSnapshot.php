<?php

namespace App\Services\Helpdesk;

use App\Models\DocumentRequest;
use App\Models\DocumentRequestComment;
use App\Models\DocumentRequestHistory;
use App\Models\OdDocument;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;

/**
 * TicketSnapshot (kontrak integrasi helpdesk §3.4): keadaan terakhir satu Permintaan Dokumen dalam bentuk yang
 * dimengerti helpdesk. Dipakai oleh GET tickets/{ref}, respons POST tickets, dan webhook ticket.updated.
 */
class TicketSnapshot
{
    public const OPEN = 'open';

    public const IN_PROGRESS = 'in_progress';

    public const DONE = 'done';

    public const CANCELLED = 'cancelled';

    /** Masa berlaku link file rilis di snapshot; helpdesk mendapat link baru setiap snapshot dikirim/dimuat ulang. */
    public const FILE_LINK_DAYS = 7;

    /**
     * @return array<string, mixed>
     */
    public static function for(DocumentRequest $request): array
    {
        $request->loadMissing(['category', 'histories', 'comments.user']);
        // Pengajuan yang dihapus tetap dikirim (sebagai dibatalkan) beserta item-itemnya yang ikut terhapus.
        $processes = $request->trashed()
            ? $request->processes()->withTrashed()->with(['status', 'pic'])->get()
            : $request->processes()->with(['status', 'pic'])->get();
        $request->setRelation('processes', $processes);

        return [
            'ref' => $request->code,
            'url' => route('document-request.track', ['kode' => $request->code]),
            'status' => self::status($request, $processes),
            'summary' => self::summary($request, $processes),
            'items' => $processes->map(fn (OdDocument $process) => [
                'title' => $process->title,
                'status' => ['code' => self::itemCode($process), 'label' => $process->publicStage()],
                'pic' => $process->pic?->nama_lengkap,
            ])->values()->all(),
            'timeline' => $request->histories->sortBy([['created_at', 'asc'], ['id', 'asc']])->map(fn (DocumentRequestHistory $history) => [
                'at' => self::time($history->created_at),
                'label' => self::historyLabel($history, $processes),
                'note' => $history->note,
            ])->values()->all(),
            'comments' => $request->comments->sortBy([['created_at', 'asc'], ['id', 'asc']])->map(fn (DocumentRequestComment $comment) => [
                'id' => (string) $comment->getKey(),
                'author' => $comment->user?->nama_lengkap ?? 'Tim OD',
                'body' => $comment->body,
                'at' => self::time($comment->created_at),
            ])->values()->all(),
            'files' => $request->trashed() ? [] : $processes
                ->filter(fn (OdDocument $process) => $process->hasFile() && $process->isReleased() && ! $process->trashed())
                ->map(fn (OdDocument $process) => [
                    'name' => $process->file_name ?: basename($process->file_path),
                    'url' => URL::temporarySignedRoute('document-request.file', now()->addDays(self::FILE_LINK_DAYS), ['document' => $process->getKey()]),
                ])->values()->all(),
            'updated_at' => self::time(self::updatedAt($request, $processes)),
        ];
    }

    /**
     * Semua item batal → cancelled; semua item selesai/batal dengan minimal satu selesai → done; ada item yang sudah
     * lewat "Pengajuan diterima" → in_progress; selain itu open.
     *
     * @param  Collection<int, OdDocument>  $processes
     * @return array{code: string, label: string}
     */
    public static function status(DocumentRequest $request, Collection $processes): array
    {
        if ($request->trashed()) {
            return ['code' => self::CANCELLED, 'label' => 'Dihapus'];
        }

        $codes = $processes->map(fn (OdDocument $process) => self::itemCode($process));
        $code = match (true) {
            $codes->isEmpty() => self::OPEN,
            $codes->every(fn (string $c) => $c === self::CANCELLED) => self::CANCELLED,
            $codes->every(fn (string $c) => in_array($c, [self::DONE, self::CANCELLED], true)) => self::DONE,
            $codes->contains(fn (string $c) => in_array($c, [self::IN_PROGRESS, self::DONE], true)) => self::IN_PROGRESS,
            default => self::OPEN,
        };

        return ['code' => $code, 'label' => $request->stage()];
    }

    public static function itemCode(OdDocument $process): string
    {
        return match (true) {
            (bool) $process->status?->isCancel() => self::CANCELLED,
            $process->isReleased() || (bool) $process->status?->isDone() => self::DONE,
            $process->stageIndex() > 0 => self::IN_PROGRESS,
            default => self::OPEN,
        };
    }

    /**
     * @param  Collection<int, OdDocument>  $processes
     * @return list<array{label: string, value: string}>
     */
    private static function summary(DocumentRequest $request, Collection $processes): array
    {
        $rows = [
            'Kode' => $request->code,
            'Kategori' => $request->category?->name,
            'Badan usaha' => $request->badan_usaha,
            'Departemen' => $request->departemen,
            'Jumlah dokumen' => (string) $processes->count(),
            'Progres' => $request->trashed() ? null : $request->progress().'%',
            'Jadwal observasi' => $request->jadwal_observasi?->locale('id')->translatedFormat('l, d F Y'),
        ];

        return collect($rows)->filter(fn (?string $value) => filled($value))
            ->map(fn (string $value, string $label) => ['label' => $label, 'value' => $value])->values()->all();
    }

    /**
     * @param  Collection<int, OdDocument>  $processes
     */
    private static function historyLabel(DocumentRequestHistory $history, Collection $processes): string
    {
        $title = $history->od_document_id ? $processes->firstWhere('id', $history->od_document_id)?->title : null;

        return $title ? "{$title}: {$history->status}" : $history->status;
    }

    /**
     * Waktu perubahan terakhir: pengajuan, item, atau komentar (judul item bisa berubah tanpa menyentuh pengajuan).
     *
     * @param  Collection<int, OdDocument>  $processes
     */
    private static function updatedAt(DocumentRequest $request, Collection $processes): Carbon
    {
        return collect([$request->updated_at, $request->deleted_at, $processes->max('updated_at'), $request->comments->max('created_at')])
            ->filter()->map(fn ($time) => Carbon::parse($time))->max();
    }

    private static function time(mixed $time): ?string
    {
        return $time ? Carbon::parse($time)->toIso8601String() : null;
    }
}
