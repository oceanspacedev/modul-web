<?php

namespace App\Services;

use App\Models\DocumentRequestHistory;
use App\Models\OdDocument;
use App\Models\OdDocumentStatus;
use App\Models\User;
use App\Notifications\DocumentRequestUpdated;
use App\Support\StorageUrl;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Perubahan tahap/status dokumen OD. Untuk dokumen dari Permintaan Dokumen, setiap perubahan tahap yang terlihat
 * pemohon dicatat di timeline pengajuan dan (kecuali $notify = false) dikabarkan ke pemohon.
 */
class OdDocumentService
{
    /**
     * Simpan file dokumen ke disk upload (menggantikan file lama). Ekstensi diambil dari isi file, bukan nama kiriman.
     */
    public function attachFile(OdDocument $document, UploadedFile $file): void
    {
        $old = $document->file_path;
        $path = $file->storeAs('od-dokumen/'.now()->format('Y-m'), Str::uuid().'.'.$file->extension());

        $document->forceFill([
            'file_path' => $path,
            'file_name' => mb_substr($file->getClientOriginalName(), 0, 200),
        ])->save();

        if ($old && $old !== $path) {
            Storage::delete($old);
        }
    }

    /**
     * Tampilkan file dokumen: disk S3 lewat URL sementara, disk lain dialirkan langsung (PDF terbuka di browser).
     */
    public function fileResponse(OdDocument $document): Response
    {
        if (Storage::disk()->providesTemporaryUrls()) {
            return redirect()->away(StorageUrl::for($document->file_path, 10));
        }

        abort_unless(Storage::exists($document->file_path), 404);

        return Storage::response($document->file_path, $document->file_name);
    }

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
            $file = $document->isReleased() && $document->hasFile() ? "\nBuka file: {$document->fileLink()}" : '';
            DocumentRequestUpdated::send($document->documentRequest, "Proses \"{$document->title}\" sekarang: {$after}.{$file}");
        }

        return true;
    }
}
