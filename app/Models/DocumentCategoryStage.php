<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu tahapan pengerjaan dalam Kategori Dokumen. Nama tahap disimpan apa adanya di od_documents.stage.
 */
class DocumentCategoryStage extends Model
{
    protected $guarded = ['id'];

    /** @return BelongsTo<DocumentCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(DocumentCategory::class, 'document_category_id');
    }
}
