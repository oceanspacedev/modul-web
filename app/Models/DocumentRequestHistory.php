<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris timeline pengajuan yang terlihat pemohon di halaman lacak: dibuat, tahap proses berubah, jadwal
 * observasi ditetapkan, atau catatan dari tim OD.
 */
class DocumentRequestHistory extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<DocumentRequest, $this> */
    public function documentRequest(): BelongsTo
    {
        return $this->belongsTo(DocumentRequest::class);
    }

    /** @return BelongsTo<OdDocument, $this> */
    public function process(): BelongsTo
    {
        return $this->belongsTo(OdDocument::class, 'od_document_id')->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
