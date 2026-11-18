<?php

namespace App\Services;

use App\Models\DocumentRequest;
use App\Models\DocumentRequestHistory;
use App\Models\OdDocument;
use App\Models\OdDocumentStatus;
use App\Models\OdPicDefault;
use App\Models\User;
use App\Notifications\DocumentRequestUpdated;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Membuat dan memproses Permintaan Dokumen. Setiap proses menjadi OdDocument jenis IK dengan PIC bawaan IK.
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
            $request = DB::transaction(function () use ($data, $user, $key) {
                $request = DocumentRequest::create([
                    ...collect($data)->only(['nama', 'divisi', 'departemen', 'jabatan', 'atasan', 'whatsapp', 'email', 'obs_tanggal_1', 'obs_tanggal_2', 'obs_catatan'])->all(),
                    'code' => DocumentRequest::generateCode(),
                    'user_id' => $user?->getKey(),
                    'idempotency_key' => $key ?: (string) Str::uuid(),
                    'tanggal' => today(),
                ]);

                $open = OdDocumentStatus::firstOfType(OdDocumentStatus::TYPE_OPEN);
                $pic = OdPicDefault::userFor('IK');

                foreach (array_values($data['proses']) as $index => $process) {
                    $request->processes()->create([
                        'title' => $process['judul'],
                        'jenis' => 'IK',
                        'badan_usaha' => '-',
                        'od_document_status_id' => $open->getKey(),
                        'pic_user_id' => $pic?->getKey(),
                        'submitted_at' => today(),
                        'month' => today()->format('Y-m'),
                        'tujuan' => $process['tujuan'],
                        'prioritas' => $process['prioritas'],
                        'sistem' => $process['sistem'] ?? null,
                        'stage' => array_key_first(OdDocument::STAGES),
                        'sort' => $index + 1,
                    ]);
                }

                DocumentRequestHistory::create([
                    'document_request_id' => $request->getKey(),
                    'status' => array_key_first(OdDocument::STAGES),
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

        $count = count($data['proses']);
        DocumentRequestUpdated::send($request, "Pengajuan {$count} proses sudah kami terima dan akan ditinjau tim Organization Development.");

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
        $isLeader = $by->can('manage-od-report');

        DB::transaction(function () use ($request, $data, $by, $isLeader, &$changes) {
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

                if (! empty($input['stage']) && $input['stage'] !== $process->stage && $this->documents->changeStage($process, $input['stage'], $by, notify: false)) {
                    $changes[] = "• {$process->title}: {$process->publicStage()}";
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

            if (filled($data['catatan_publik'] ?? null)) {
                $this->history($request, $by, $request->stage(), $data['catatan_publik']);
                $changes[] = 'Catatan: '.$data['catatan_publik'];
            }

            $request->catatan_admin = $data['catatan_admin'] ?? null;
            $request->save();
            $request->touch();
        });

        if ($changes) {
            DocumentRequestUpdated::send($request, "Ada pembaruan:\n".implode("\n", $changes));
        }
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
