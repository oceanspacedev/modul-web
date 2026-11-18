<?php

namespace App\Services;

use App\Models\DocumentRequestHistory;
use App\Models\OdDocument;
use App\Models\OdDocumentStatus;
use App\Models\User;
use App\Notifications\DocumentRequestUpdated;

/**
 * Perubahan tahap/status dokumen OD. Untuk dokumen dari Permintaan Dokumen, setiap perubahan tahap yang terlihat
 * pemohon dicatat di timeline pengajuan dan (kecuali $notify = false) dikabarkan ke pemohon.
 */
class OdDocumentService
{
    /**
     * @return bool true bila tahap yang dilihat pemohon berubah
     */
    public function changeStage(OdDocument $document, string $stage, User $by, bool $notify = true): bool
    {
        $before = $document->publicStage();
        $document->applyStage($stage);

        return $this->saveAndRecord($document, $before, $by, $notify);
    }

    /**
     * @return bool true bila tahap yang dilihat pemohon berubah
     */
    public function changeStatus(OdDocument $document, OdDocumentStatus $status, User $by, bool $notify = true): bool
    {
        $before = $document->publicStage();
        $document->applyStatus($status);

        return $this->saveAndRecord($document, $before, $by, $notify);
    }

    private function saveAndRecord(OdDocument $document, string $before, User $by, bool $notify): bool
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
            'user_id' => $by->getKey(),
        ]);
        $document->documentRequest->touch();

        if ($notify) {
            DocumentRequestUpdated::send($document->documentRequest, "Proses \"{$document->title}\" sekarang: {$after}.");
        }

        return true;
    }
}
