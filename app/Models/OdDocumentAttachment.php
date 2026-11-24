<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * File dari pemohon Permintaan Dokumen untuk satu dokumen: lampiran pendukung uraian, atau template yang sudah diisi
 * (kategori bertemplate seperti Jobdesk). Hanya tim OD yang bisa membukanya.
 */
class OdDocumentAttachment extends Model
{
    public const KIND_LAMPIRAN = 'lampiran';

    public const KIND_TEMPLATE = 'template';

    /** Lampiran uraian: Word, PDF, Excel, atau Visio. */
    public const LAMPIRAN_EXTENSIONS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'vsd', 'vsdx'];

    /** Template terisi: Word, Excel, atau PDF. */
    public const TEMPLATE_EXTENSIONS = ['doc', 'docx', 'xls', 'xlsx', 'pdf'];

    public const MAX_FILES = 5;

    /** Batas ukuran per file dan total per dokumen, dalam KB. */
    public const MAX_KB = 10240;

    protected $guarded = ['id'];

    /** @return BelongsTo<OdDocument, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(OdDocument::class, 'od_document_id')->withTrashed();
    }

    public function sizeLabel(): string
    {
        return $this->size >= 1048576 ? number_format($this->size / 1048576, 1, ',', '.').' MB' : max(1, (int) round($this->size / 1024)).' KB';
    }

    public function icon(): string
    {
        return match (strtolower(pathinfo($this->name, PATHINFO_EXTENSION))) {
            'pdf' => 'fa-file-pdf',
            'doc', 'docx' => 'fa-file-word',
            'xls', 'xlsx' => 'fa-file-excel',
            'vsd', 'vsdx' => 'fa-project-diagram',
            default => 'fa-file',
        };
    }
}
