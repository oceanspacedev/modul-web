<?php

namespace App\Services;

use App\Models\DocumentRequestHistory;
use App\Models\OdDocument;
use App\Models\OdDocumentStatus;
use App\Models\User;
use App\Notifications\DocumentRequestUpdated;

/**
 * Perubahan tahap/status dokumen OD. Untuk dokumen dari Permintaan Dokumen, setiap perubahan tahap yang terlihat
 * pemohon dicatat di timeline pengajuan dan dikabarkan ke pemohon.
 */
class OdDocumentService
{
    public function changeStage(OdDocument $document, string $stage, User $by, ?string $note = null): bool
    {
        $before = $document->publicStage();
        $document->applyStage($stage);

        return $this->saveAndRecord($document, $before, $by, $note);
    }

    public function changeStatus(OdDocument $document, OdDocumentStatus $status, User $by): bool
    {
        $before = $document->publicStage();
        $document->applyStatus($status);

        return $this->saveAndRecord($document, $before, $by);
    }

    /**
     * Simpan dokumen; kalau tahap yang dilihat pemohon berubah, catat di timeline dan kabari pemohon.
     *
     * @return bool true bila tahap pemohon berubah
     */
    private function saveAndRecord(OdDocument $document, string $before, User $by, ?string $note = null): bool
    {
        $document->save();
        $document->load('status');

        $after = $document->publicStage();
        if (! $document->isFromRequest() || $after === $before) {
            return false;
        }

        DocumentRequestHistory::create([
            'document_request_id' => $document->document_request_id,
            'od_document_id' => $document->getKey(),
            'status' => $after,
            'note' => $note,
            'user_id' => $by->getKey(),
        ]);

        DocumentRequestUpdated::send($document->documentRequest, "Proses \"{$document->title}\" sekarang: {$after}.");

        return true;
    }
}
