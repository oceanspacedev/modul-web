<?php

namespace App\Services;

use App\Models\DocumentCategory;
use App\Models\DocumentRequest;
use App\Models\DocumentRequestComment;
use App\Models\DocumentRequestHistory;
use App\Models\OdDocument;
use App\Models\OdDocumentAttachment;
use App\Models\OdDocumentStatus;
use App\Models\User;
use App\Notifications\DocumentRequestSubmitted;
use App\Notifications\DocumentRequestUpdated;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Membuat dan memproses Permintaan Dokumen. Setiap item menjadi OdDocument dengan jenis dan PIC bawaan dari Kategori
 * Dokumen yang dipilih pemohon, berstatus awal Pengajuan diterima, beserta lampiran atau template terisinya.
 */
class DocumentRequestService
{
    public function __construct(private OdDocumentService $documents) {}

    /**
     * @param  array<string, mixed>  $data  data tervalidasi dari StoreDocumentRequestRequest
     */
    public function create(array $data, ?User $user): DocumentRequest
    {
        $key = $data['idempotency_key'] ?? null;
        if ($key && ($existing = DocumentRequest::where('idempotency_key', $key)->first())) {
            return $existing;
        }

        try {
            $category = DocumentCategory::findOrFail($data['kategori']);
            $firstStage = OdDocument::STAGE_RECEIVED;
            $template = $category->hasTemplate();

            $request = DB::transaction(function () use ($data, $user, $key, $category, $firstStage, $template) {
                $observation = $category->needs_observation
                    ? collect($data)->only(['obs_tanggal_1', 'obs_tanggal_2', 'obs_catatan'])->all()
                    : ['obs_tanggal_1' => null, 'obs_tanggal_2' => null, 'obs_catatan' => null];

                $request = DocumentRequest::create([
                    ...collect($data)->only(['nama', 'divisi', 'departemen', 'jabatan', 'atasan', 'whatsapp', 'email', 'badan_usaha'])->all(),
                    ...$observation,
                    'code' => DocumentRequest::generateCode($category->ticketPrefix()),
                    'document_category_id' => $category->getKey(),
                    'user_id' => $user?->getKey(),
                    'idempotency_key' => $key ?: (string) Str::uuid(),
                    'tanggal' => today(),
                ]);

                $open = OdDocumentStatus::firstOfType(OdDocumentStatus::TYPE_OPEN);
                $pic = $category->picUser();

                foreach (array_values($data['proses']) as $index => $process) {
                    $document = $request->processes()->create([
                        'title' => $process['judul'],
                        'jenis' => $category->code,
                        'badan_usaha' => $data['badan_usaha'],
                        'od_document_status_id' => $open->getKey(),
                        'pic_user_id' => $pic?->getKey(),
                        'submitted_at' => today(),
                        'month' => today()->format('Y-m'),
                        'tujuan' => $template ? null : $process['tujuan'],
                        'jenis_pengajuan' => $template ? $process['jenis_pengajuan'] : null,
                        'prioritas' => $process['prioritas'],
                        'sistem' => $template ? null : ($process['sistem'] ?? null),
                        'stage' => $firstStage,
                        'sort' => $index + 1,
                    ]);

                    $files = $template ? array_filter([$process['template'] ?? null]) : ($process['lampiran'] ?? []);
                    foreach ($files as $file) {
                        $this->documents->addAttachment($document, $file, $template ? OdDocumentAttachment::KIND_TEMPLATE : OdDocumentAttachment::KIND_LAMPIRAN);
                    }
                }

                DocumentRequestHistory::create([
                    'document_request_id' => $request->getKey(),
                    'status' => $firstStage,
                    'note' => 'Pengajuan diterima.',
                    'user_id' => $user?->getKey(),
                ]);

                return $request;
            });
        } catch (UniqueConstraintViolationException $e) {
            // Kiriman ganda yang datang bersamaan: kembalikan pengajuan yang sudah tersimpan.
            if ($key && ($existing = DocumentRequest::where('idempotency_key', $key)->first())) {
                return $existing;
            }
            throw $e;
        }

        $titles = collect($data['proses'])->values()->map(fn ($process, $i) => ($i + 1).'. '.$process['judul'])->implode("\n");
        DocumentRequestUpdated::send($request, "Pengajuan dokumen {$category->name} Anda sudah kami terima.\n"
            ."Kode lacak: *{$request->code}*\n{$titles}\n\nSimpan kode ini untuk melacak progres.");

        $request->setRelation('category', $category);
        DocumentRequestSubmitted::sendToManagers($request);

        return $request;
    }

    /**
     * Simpan perubahan dari halaman Permintaan Masuk. Staff hanya bisa mengubah proses yang PIC-nya dia.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(DocumentRequest $request, array $data, User $by): void
    {
        $changes = [];
        $attachments = [];
        $isLeader = $by->can('manage-od-report');

        DB::transaction(function () use ($request, $data, $by, $isLeader, &$changes, &$attachments) {
            $request->loadMissing('processes.status');

            foreach ($request->processes as $process) {
                $process->setRelation('documentRequest', $request);
                $input = $data['proses'][$process->id] ?? null;
                if (! $input || ! $process->canBeEditedBy($by)) {
                    continue;
                }

                if ($isLeader && array_key_exists('pic_user_id', $input)) {
                    $process->pic_user_id = $input['pic_user_id'];
                }
                if (! empty($input['prioritas']) && $input['prioritas'] !== $process->prioritas) {
                    $process->prioritas = $input['prioritas'];
                    $this->history($request, $by, $process->publicStage(), "Prioritas \"{$process->title}\" menjadi {$input['prioritas']}.", $process);
                }

                if (($input['file'] ?? null) instanceof UploadedFile) {
                    $this->documents->attachFile($process, $input['file']);
                }

                if (! empty($input['stage']) && $input['stage'] !== $process->stage && $this->documents->changeStage($process, $input['stage'], $by, notify: false)) {
                    $attachment = $process->isReleased() ? $process->whatsAppAttachment() : null;
                    $changes[] = "• {$process->title}: {$process->publicStage()}".($attachment ? ' (file terlampir)' : '');
                    if ($attachment) {
                        $attachments[] = $attachment;
                    }
                } else {
                    $process->save();
                }
            }

            $schedule = $data['jadwal_observasi'] ?? null;
            if ($schedule !== $request->jadwal_observasi?->toDateString()) {
                $request->jadwal_observasi = $schedule;
                $label = $schedule ? 'Jadwal observasi: '.$request->jadwal_observasi->locale('id')->translatedFormat('l, d F Y').'.' : 'Jadwal observasi dibatalkan.';
                $this->history($request, $by, $request->stage(), $label);
                $changes[] = $label;
            }

            $request->catatan_admin = $data['catatan_admin'] ?? null;
            $request->save();
            $request->touch();
        });

        if ($changes) {
            DocumentRequestUpdated::send($request, "Ada pembaruan:\n".implode("\n", $changes), $attachments);
        }
    }

    /**
     * Komentar tim OD untuk pemohon: tampil di halaman lacak dan dikirim ke WhatsApp pemohon.
     */
    public function comment(DocumentRequest $request, string $body, User $by): DocumentRequestComment
    {
        $comment = $request->comments()->create(['body' => $body, 'user_id' => $by->getKey()]);
        $request->touch();

        DocumentRequestUpdated::send($request, "Komentar dari tim OD ({$by->nama_lengkap}):\n{$body}");

        return $comment;
    }

    private function history(DocumentRequest $request, User $by, string $status, string $note, ?OdDocument $process = null): void
    {
        DocumentRequestHistory::create([
            'document_request_id' => $request->getKey(),
            'od_document_id' => $process?->getKey(),
            'status' => $status,
            'note' => mb_substr($note, 0, 300),
            'user_id' => $by->getKey(),
        ]);
    }
}
