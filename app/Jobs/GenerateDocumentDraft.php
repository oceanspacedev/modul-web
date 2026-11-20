<?php

namespace App\Jobs;

use App\Models\DocumentDraft;
use App\Services\DocumentBuilderAiService;

/**
 * Dokumen Builder: AI menyusun isi draf dari jawaban form bertahap. Menunggu Gemini bisa sampai beberapa menit, jadi
 * dikerjakan worker AI; halaman proses mengambil hasilnya lewat polling (DocumentBuilderController::aiStatus).
 */
class GenerateDocumentDraft extends AiTask
{
    public function __construct(string $token, public int $draftId)
    {
        parent::__construct($token);
    }

    protected function run(): array
    {
        $draft = DocumentDraft::with(['category', 'odDocument.documentRequest'])->findOrFail($this->draftId);

        $draft->update(['content' => (new DocumentBuilderAiService)->compose($draft)]);

        // Halaman proses sudah tahu alamat editornya; cukup tandai selesai.
        return [];
    }
}
